<?php



class DFS_ToDoListPlugin {
    public function __construct() {
        register_activation_hook(__FILE__, [$this,'create_todo_database']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('wp_ajax_add_todo_project', [$this,'handle_add_todo_project']);
        add_action('wp_ajax_add_todo_task', [$this,'handle_add_todo_task']);
        add_action('wp_ajax_update_todo_task_status', [$this,'handle_update_todo_task_status']);
        add_action('wp_ajax_update_todo_order', [$this,'handle_update_todo_order']);
        add_action('wp_ajax_delete_todo_project', [$this,'handle_delete_todo_project']);
        add_action('wp_ajax_delete_todo_task', [$this,'handle_delete_todo_task']);
        add_action('wp_ajax_recreate_tasks_table', [$this, 'handle_recreate_tasks_table']);
        add_action('admin_enqueue_scripts', [$this, 'myplugin_enqueue_media_uploader']);
    }

    function myplugin_enqueue_media_uploader($hook) {
        if ($hook !== 'defacto-starter_page_df-todo') {
        return;
        }

        wp_enqueue_script(
            'df-todo-list', 
            MY_PLUGIN_URL . '/assets/js/df-todo-list.js',
            ['jquery'], 
            '1.0', 
            true
        );
            
        wp_localize_script('df-todo-list', 'myplugin_ajax', [
            'ajax_url' => admin_url('admin-ajax.php')
        ]);
    }

    public function add_settings_page()
    {
        add_submenu_page(
            'df-settings',
            'To-do list',
            'To-do list',
            'manage_options',
            'df-todo',
            [$this, 'render_todo_page']
        );
    }

    public function create_todo_database() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        $project_table_name = $wpdb->prefix . 'dfs_todo_projects';
        $project_sql = "CREATE TABLE $project_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            project_name varchar(255) NOT NULL,
            is_done tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $task_table_name = $wpdb->prefix . 'todo_tasks';
        $task_sql = "CREATE TABLE $task_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            project_id mediumint(9) NOT NULL,
            task_order mediumint(9) NOT NULL,
            task TEXT NOT NULL,
            is_done tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            FOREIGN KEY (project_id) REFERENCES $project_table_name(id) ON DELETE CASCADE
        ) $charset_collate;";

        dbDelta($project_sql);
        dbDelta($task_sql);
    }

    public function handle_recreate_tasks_table() {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        $project_table_name = $wpdb->prefix . 'todo_projects';
        $task_table_name = $wpdb->prefix . 'todo_tasks';

        // Drop the table, be sure this is what you want
        $drop_result = $wpdb->query("DROP TABLE IF EXISTS $task_table_name");
        if ($drop_result === false) {
            wp_send_json_error(['message' => 'Failed to drop tasks table: ' . $wpdb->last_error]);
            wp_die();
        }

        // Create table SQL
        $task_sql = "CREATE TABLE $task_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            project_id mediumint(9) NOT NULL,
            task_order mediumint(9) NOT NULL,
            task TEXT NOT NULL,
            is_done tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            FOREIGN KEY (project_id) REFERENCES $project_table_name(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Run dbDelta to create the table
        dbDelta($task_sql);

        // Check if there was an error after dbDelta
        if (!empty($wpdb->last_error)) {
            wp_send_json_error(['message' => 'Failed to create tasks table: ' . $wpdb->last_error]);
            wp_die();
        }

        wp_send_json_success(['message' => 'Tasks table recreated successfully']);
        wp_die();
    }

    public function handle_add_todo_project()
    {
        global $wpdb;

        $project_name = sanitize_text_field($_POST['project_name']);
        $is_done = intval($_POST['is_done']);

        $project_table_name = $wpdb->prefix . 'todo_projects';
        $inserted = $wpdb->insert($project_table_name, [
            "project_name" => $project_name,
            "is_done" => $is_done 
        ]);

        if ($inserted !== false) {
            $last_id = $wpdb->insert_id;
            wp_send_json_success([
                'message' => 'Project added successfully',
                'last_id' => $last_id,
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to add project']);
        }
        wp_die();
    }

    public function handle_add_todo_task()
    {
        global $wpdb;

        $project_id = intval($_POST['project_id']);
        $task = sanitize_text_field($_POST['task']);
        $is_done = intval($_POST['is_done']);

        $task_index = $this->get_total_task_count_in_project($project_id);

        $task_table_name = $wpdb->prefix . 'todo_tasks';
        $inserted = $wpdb->insert($task_table_name, [
            "project_id" => $project_id,
            "task" => $task,
            "is_done" => $is_done,
            "task_order" => $task_index
        ]);

        if ($inserted !== false) {
            $last_id = $wpdb->insert_id;
            wp_send_json_success([
                'message' => 'Task added successfully',
                'last_id' => $last_id,
                'order' => $task_index
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to add task']);
        }
        wp_die();
    }

    public function get_tasks_by_id()
    {
        global $wpdb;
        $task_table_name = $wpdb->prefix . 'todo_tasks';
        $tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $task_table_name ORDER BY id ASC"
            )
        );
        return $tasks;
    }

    public function get_tasks_by_order()
    {
        global $wpdb;
        $task_table_name = $wpdb->prefix . 'todo_tasks';
        $tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $task_table_name ORDER BY task_order ASC"
            )
        );
        return $tasks;
    }

    public function get_tasks_by_id_and_project(int $project_id)
    {
        global $wpdb;
        $task_table_name = $wpdb->prefix . 'todo_tasks';
        $tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $task_table_name WHERE project_id = %d ORDER BY id ASC",
                $project_id
            )
        );
        return $tasks;
    }

    public function get_tasks_by_order_and_project(int $project_id)
    {
        global $wpdb;
        $task_table_name = $wpdb->prefix . 'todo_tasks';
        $tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $task_table_name WHERE project_id = %d ORDER BY task_order ASC",
                $project_id
            )
        );
        return $tasks;
    }

    public function handle_update_todo_task_status()
    {
        global $wpdb;

        $task_id = intval($_POST['task_id']);
        $is_done = intval($_POST['is_done']);

        $task_table_name = $wpdb->prefix . 'todo_tasks';
        $updated = $wpdb->update(
            $task_table_name, 
            ['is_done' => $is_done], 
            ['id' => $task_id]
        );

        if ($updated !== false) {
            wp_send_json_success(['message' => 'Task updated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to update task']);
        }
        wp_die();
    }

    public function handle_update_todo_order()
    {
        global $wpdb;

        $project_id = intval($_POST['project_id']);
        $json_data = stripslashes($_POST['json_data']);
        $task_order = json_decode($json_data, true);

        $task_table_name = $wpdb->prefix . 'todo_tasks';
        if (!is_array($task_order)) {
            wp_send_json_error(['message' => 'Invalid order data']);
            wp_die();
        }

        foreach ($task_order as $task_id => $order) {
            $updated = $wpdb->update(
                $task_table_name,
                ['task_order' => $order, 'project_id' => $project_id],
                ['id' => intval($task_id)]
            );

            if ($updated === false) {
                wp_send_json_error(['message' => 'Failed to update task order']);
                wp_die();
            }
        }

        wp_send_json_success(['message' => 'Task updated successfully']);
        wp_die();
    }

    public function handle_delete_todo_project()
    {
        global $wpdb;

        $project_id = intval($_POST['project_id']);

        $task_table_name = $wpdb->prefix . 'todo_tasks';
        $tasks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $task_table_name WHERE project_id = %d ORDER BY id ASC",
                $project_id
            )
        );

        foreach ($tasks as $task)
        {
            $task_table_name = $wpdb->prefix . 'todo_tasks';
            $deleted_task = $wpdb->delete($task_table_name, ['id' => intval($task->id)]);
            if ($deleted_task === false) {
                wp_send_json_error(['message' => 'An error occured when deleting a task']);
                wp_die();
            }
        }

        $project_table_name = $wpdb->prefix . 'todo_projects';
        $deleted = $wpdb->delete($project_table_name, ['id' => $project_id]);

        if ($deleted !== false) {
            wp_send_json_success(['message' => 'Project deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete project']);
        }
        wp_die();
    }

    public function handle_delete_todo_task()
    {
        global $wpdb;

        $task_id = intval($_POST['task_id']);

        $task_table_name = $wpdb->prefix . 'todo_tasks';
        $deleted = $wpdb->delete($task_table_name, ['id' => $task_id]);

        if ($deleted !== false) {
            wp_send_json_success(['message' => 'Task deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to add delete']);
        }
        wp_die();
    }

    public function get_total_task_count_in_project(int $project_id)
    {
        global $wpdb;

        $task_table_name = $wpdb->prefix . 'todo_tasks';
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $task_table_name WHERE project_id = %d",
                $project_id
            )
        );

        return (int) $count;
    }

    public function render_todo_page() {
        // appel de données de quelque part.
        global $wpdb;

        // Table names with prefix
        $project_table = $wpdb->prefix . 'todo_projects';
        $task_table = $wpdb->prefix . 'todo_tasks';

        // Get all projects
        $projects = $wpdb->get_results("SELECT * FROM $project_table ORDER BY id ASC");

        $donnees = [];

        foreach ($projects as $project) {
            $project_name = esc_html($project->project_name);
            $donnees[$project_name] = [
                'id' => $project->id,
                'tasks' => []
            ];

            $tasks = $this->get_tasks_by_order_and_project(intval($project->id));

            if ($tasks) {
                foreach ($tasks as $task) {
                    $donnees[$project_name]['tasks'][$task->task] = [
                        'id' => $task->id,
                        'order' => intval($task->task_order),
                        'is_done' => intval($task->is_done)
                    ];
                }
            }
        }

        ?>
        <link href="<?=MY_PLUGIN_URL?>styles/df-todo-list.css" rel="stylesheet"/>
        <div id="task-container" class="task-container">
          <?php foreach ($donnees as $project_name => $project_data) : ?>
            <div data-id="<?=$project_data['id']?>" id="project_<?=$project_data['id']?>" class="project" ondragover="handleDragOver(event)" ondrop="handleDrop(event)">
              <h3><?= esc_html($project_name) ?></h3>
              <button class="delete-project-btn" onclick="delete_project(<?= $project_data['id'] ?>)">Delete</button>
              <div class="add-section">
                  <input type="text" id="new-task-name-<?=$project_data['id']?>" placeholder="Nom de la tâche" />
                  <button id="add-task-btn" data-id="<?=$project_data['id']?>" onclick="add_task(event)">Ajouter tâche</button>
                </div>
              <?php foreach ($project_data['tasks'] as $task_name => $task_data) : ?>
                <div data-id="<?=$task_data['id']?>" data-order="<?=$task_data['order']?>" id="task-<?=$task_data['id']?>" class="task" draggable="true" ondragstart="handleDragStart(event)">
                  <span><?= esc_html($task_name)?></span>
                  <span data-id="<?=$task_data['id']?>" class="toggle-btn <?= $task_data['is_done'] ? 'done' : '' ?>" onclick="toggleDone(this)"></span>
                  <button class="delete-task-btn" onclick="delete_task(<?= $task_data['id'] ?>)">×</button>
                </div>
              <?php endforeach ?>
            </div>
          <?php endforeach ?>
        </div>

        <div class="add-section">
          <!-- Add new Project -->
          <h3>Ajouter un projet</h3>
          <input type="text" id="new-project-name" placeholder="Nom du projet" />
          <button id="add-project-btn" onclick="add_project(event)">Ajouter projet</button>
        </div>
        <?php
    }
}