# jira-dependency-renderer
Plot dependencies between Jira tickets.

## Setup

- Composer install

- Edit settings.php and enter your Jira credentials.

- Edit dependency-render.php and enter your JQL query.

- Install Graphviz: sudo apt install dot

## Operation
 - php dependency-render.php > graph

 - twopi -Tjpg -O graph
