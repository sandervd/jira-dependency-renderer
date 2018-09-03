<?php

use GuzzleHttp\Client;
class jira {

  protected $nodes = [];

  protected $blocks = [];
  /**
   * @var Client GuzzleClient
   */
  protected $conn = NULL;

  function __construct()
  {
    $this->conn = new Client([
      'base_uri' => JIRA_HOST,
      'timeout' => 15.0,
    ]);
    $this->auth = [JIRA_USERNAME, JIRA_PASSWORD];
  }

  /**
   * @param $nodes
   * @param $blocks
   */
  public function render() {
    $out = "digraph G {\n";
    $out .= "overlap = false; pad=\"0\"; nodesep=\"0\"; ranksep=\"0.4 equally\";";

    $nodes_to_render = [];
    $edges = '';
    foreach ($this->blocks as $source => $target) {
      $target_values = array_keys($target);
      foreach ($target_values as $target_value) {
        $edges .=  '"' . $source . '"->"' . $target_value . "\";\n";
        $nodes_to_render[$source] = $source;
        $nodes_to_render[$target_value] = $target_value;

      }
    }
    foreach ($this->nodes as $key => $summary) {
      if (isset($nodes_to_render[$key])) {
        $out .= '"' . $key . '"[shape=box,label="' . $key . "\n" . str_replace('"', "'", $summary) . '",labelloc=b,fontsize=15];' . "\n";
      }
    }
    $out .= $edges;
    $out .=  '}';
    return $out;
  }

  protected function search($jql) {
    $uri = 'rest/api/2/search';
    $resp = $this->conn->post($uri, [
        'auth' => $this->auth,
        'headers' => [
          'Content-Type',
          'application/json',
        ],
        'json' => [
          "jql" => $jql,
          "startAt" => 0,
          "maxResults" => 500,
          "fields" => [
            "id",
            "key",
            "summary",
            "issuelinks",
            "subtasks"
          ]
        ]
      ]
    );

    $body = (string) $resp->getBody();
    return json_decode($body);
  }

  function getGraph($jql) {
    $obj = $this->search($jql);
    foreach ($obj->issues as $issue) {
      $this->handleIssue($issue);
    }
    return $this;
  }

  function handleIssue($issue) {
    $issue_key = $issue->key;
    $summary = $issue->fields->summary;
    $this->nodes[$issue_key] = $summary;
    $fields = $issue->fields;
    if (!empty($fields->issuelinks)) {
      $this->handleIssueLinks($fields->issuelinks, $issue_key);
    }
    if (!empty($fields->subtasks)) {
        $this->handleSubTasks($fields->subtasks, $issue_key);
    }
  }

  /**
   * Add linked issues to the graph.
   *
   * @param $fields
   * @param $issue_key
   */
  public function handleIssueLinks($issueFields, $issue_key) {
    foreach ($issueFields as $field) {
      if (isset($field->outwardIssue)) {
        $this->blocks[$field->outwardIssue->key][$issue_key] = TRUE;
        $this->nodes[$field->outwardIssue->key] = $field->outwardIssue->fields->summary;
      }
      if (isset($field->inwardIssue)) {
        $this->blocks[$issue_key][$field->inwardIssue->key] = TRUE;
        $this->nodes[$field->inwardIssue->key] = $field->inwardIssue->fields->summary;
      }
    }
  }
    /**
     * Add subtasks to the graph.
     *
     * @param $fields
     * @param $issue_key
     */
    public function handleSubTasks($subTasks, $issue_key) {
      foreach ($subTasks as $field) {
          $this->blocks[$issue_key][$field->key] = TRUE;
          $this->nodes[$field->key] = $field->fields->summary;
      }
    }
}
