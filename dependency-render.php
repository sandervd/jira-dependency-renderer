<?php
require __DIR__ . '/vendor/autoload.php';
require_once 'settings.php';
require_once 'src/jira.php';

$jira = new jira();
// Some JQL query.
print $jira->getGraph("project = OPENEUROPA AND Sprint = 12634")->render();
