<?php

use GuzzleHttp\Client;
class jira {

  protected $nodes;

  protected $blocks;
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

  public function asJson() {
    $out = [];
    $map = [];
    foreach ($this->blocks as $source => $target) {
      $target_values = array_keys($target);
      foreach ($target_values as $target_value) {
        $out['links'][] = [
          'value' => 2,
          'source' => $source,
          'target' => $target_value,
        ];
        $nodes_to_render[$source] = $source;
        $nodes_to_render[$target_value] = $target_value;

      }
    }
    foreach ($this->nodes as $key => $summary) {
      if (isset($nodes_to_render[$key])) {
        $out['nodes'][] = [
          'id' => (string) $key,
          'name' => str_replace('"', "'", $summary),
        ];
        $map[] = (string) $key;
      }
    }
    foreach ($out['links'] as $key => &$link) {
      $link['source'] = array_search($link['source'], $map);
      $link['target'] = array_search($link['target'], $map);
    }



    return \GuzzleHttp\json_encode($out);
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
            "issuelinks"
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
    $key = $issue->key;
    $summary = $issue->fields->summary;
    $this->nodes[$key] = $summary;
    $fields = $issue->fields;
    if (empty($fields->issuelinks)) {
      return;
    }
    foreach ($fields->issuelinks as $field) {
      if (isset($field->outwardIssue)) {
        $this->blocks[$field->outwardIssue->key][$key] = TRUE;
        $this->nodes[$field->outwardIssue->key] = $field->outwardIssue->fields->summary;
      }
      if (isset($field->inwardIssue)) {
        $this->blocks[$key][$field->inwardIssue->key] = TRUE;
        $this->nodes[$field->inwardIssue->key] = $field->inwardIssue->fields->summary;
      }
    }
  }
}
