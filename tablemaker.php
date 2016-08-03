<?php

/*
Plugin Name: ACG Table Maker
Plugin URI: http://www.alphachannelgroup.com/
Description: Create and display custom HTML tables
Version: 1.1.1
Author: Brian Hulbert
Author URI: http://www.alphachannelgroup.com
License: GPLv2 or later
*/


class ACGTablemaker {

    /**
     * Local instance of the database for convenience
     *
     * @var wpdb
     */
    protected $db;

    /**
     * Admin URL in the dashboard
     *
     * @var string
     */
    private $url;

    /**
     * Admin Action used to indicate desired action to take
     *
     * @var string
     */
    protected $action;

    /**
     * Array of accumulated errors for display
     *
     * @var array
     */
    private static $error = array();

    /**
     * Array of accumulated messages for display
     *
     * @var array
     */
    private static $message = array();

    private $shortcode_run = FALSE;
    // Name of table structure table
    protected $table;

    // Name of table table
    protected $table_data;

    const LANG = 'acg_tablemaker_lang';

    const OPTIONS_GROUP = 'acg_tablemaker_group';

    const OPTIONS = 'acg_tablemaker';

    const MENU_SLUG = 'acg_tablemaker';

    const SUBMENU_SLUG = 'acg_tablemaker_data';

    const NONCE_NAME = 'tablemaker_nonce';

    const NONCE_ACTION = '^^^T4b!3M4k3r^^^';

    const VERSION_KEY = 'acg_tablemaker_version';

    const PLUGIN_VERSION = '1.0';

    private $version;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;

        $this->version = get_option(self::VERSION_KEY, 0);

        $this->define_tables();

        $this->add_actions();
        $this->add_filters();
        $this->add_shortcodes();
    }

    /**
     * Set up the names of the database tables
     */
    private function define_tables() {
        global $table_prefix;

        $this->table = $table_prefix . 'tablemaker_table';
        $this->table_data = $table_prefix . 'tablemaker_table_data';
    }

    /**
     * Add the WP actions we need to run
     */
    private function add_actions() {
        $actions = array(
            'plugins_loaded',
            'init',
            'wp_footer',
            'admin_init',
            'admin_menu',
            'wp_enqueue_scripts',
            'admin_enqueue_scripts'
        );

        foreach ($actions as $action) {
            if (method_exists($this, $action)) {
                add_action($action, array($this, $action));
            }
        }
    }

    /**
     * Prints scripts in the footer if the shortcode run flag is set. Prevents loading of scripts on every page.
     */
    public function wp_footer() {
        if ($this->shortcode_run) {
            wp_print_scripts('tablesorter');
        }
    }

    /**
     * WP Plugins loaded action
     */
    public function plugins_loaded() {
        do_action('acg_tablemaker_loaded');
    }

    /**
     * WP Init action
     */
    public function init() {
        do_action('acg_tablemaker_init');
    }


    /**
     * WP enqueue scripts action
     */
    public function wp_enqueue_scripts() {
        wp_enqueue_style('acg_tablemaker', plugins_url('css/tablemaker.css', __FILE__), FALSE, self::PLUGIN_VERSION);
        wp_register_script('tablesorter', plugins_url('js/jquery.tablesorter.min.js', __FILE__), array('jquery'));
        wp_enqueue_style('acg-font-awesome', plugins_url('/acg_tablemaker/font-awesome/css/font-awesome.min.css">'));
    }

    /**
     * WP admin init action
     */
    public function admin_init() {
        register_setting(self::OPTIONS_GROUP, self::OPTIONS);
    }

    /**
     * WP admin enqueue scripts action
     */
    public function admin_enqueue_scripts() {

        $page = (isset($_GET["page"])) ? $_GET["page"] : '';
        if ($page == self::MENU_SLUG || $page == 'hp-table-settings') {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-sortable');

            wp_register_script('tablemaker_admin', plugins_url('js/jquery.tablemaker.admin.js', __FILE__), array('jquery'));
            wp_localize_script('tablemaker_admin', 'hpsAjax', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(self::NONCE_ACTION)
            ));
            wp_enqueue_script('tablemaker_admin');
            wp_enqueue_style('tablemaker_admin', plugins_url('css/tablemaker-admin.css', __FILE__));

        }
    }

    /**
     * WP add admin menu action
     */
    public function admin_menu() {
        $plugin_url = plugin_dir_url(__FILE__);

        add_menu_page($this->__('Tablemaker'), $this->__('Tablemaker'), 'manage_options', self::MENU_SLUG, array(
            $this,
            'admin_controller'
        ));

    }

    /**
     * Add all the shortcodes the plugin uses
     */
    public function add_shortcodes() {
        add_shortcode('table-maker', array($this, 'shortcode'));
    }


    /**
     * Controller method for admin dashboard
     */
    public function admin_controller() {
        $content = '';
        $this->acg_plugin_tablemaker_checktables();
        $this->url = admin_url('admin.php?page=' . self::MENU_SLUG);
        echo '<div class="wrap tablemaker">';
        echo '<h2>' . $this->__('Table Maker: Manage Tables') . '</h2>';
        $action = $this->request('action');
        if ($action) {
            $nonce = $this->request(self::NONCE_NAME);
            if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                $this->set_error('Security Error.  Try Again.');
                $action = '';
            }
        }

        switch ($action) {
            case 'edit_table':
                $content = $this->edit_table();
                break;
            case 'add_table':
                $content = $this->edit_table();
                break;
            case 'edit_table_data':
                $content = $this->build_table();
                break;
            case 'save_table_data':
                $content = $this->save_table_data();
                $content .= $this->build_table();
                break;
            case 'delete_table':
                $content = $this->delete_table();
            default:
                $content = $this->list_tables();
                break;
        }

        $this->output_errors();
        $this->output_messages();
        echo $content;
        echo '</div>';
    }

    /**
     * Admin functionality.
     * Lists all of the tables created by users
     *
     * @return string
     */
    private function list_tables() {
        $tables = $this->get_tables();
        $form = '';
        $form .= '<table class="tables">';
        $form .= '<tr class="title"><th>Table Name</th><th>Shortcode</th><th>Actions</th></tr>';
        foreach ($tables AS $table) {
            $form .= '<tr>';
            $form .= '<td>' . stripslashes($table->tablename) . '</td>';
            $form .= '<td class="shortcode">' . '[table-maker id="' . $table->id . '"]</td>';
            $form .= '<td><a href="' . wp_nonce_url($this->url, self::NONCE_ACTION, self::NONCE_NAME) . '&action=edit_table&table_id=' . $table->id . '">' . $this->__('Structure') . '</a>
						<a href="' . wp_nonce_url($this->url, self::NONCE_ACTION, self::NONCE_NAME) . '&action=edit_table_data&table_id=' . $table->id . '">' . $this->__('Data') . '</a>
						<a href="' . wp_nonce_url($this->url, self::NONCE_ACTION, self::NONCE_NAME) . '&action=delete_table&table_id=' . $table->id . '" class="warning delete">' . $this->__('Delete') . '</a>
					</td>';
            $form .= '</tr>';
        }

        $form .= '</table>';
        $form .= '<div><a class="button button-primary" href="' . wp_nonce_url($this->url, self::NONCE_ACTION, self::NONCE_NAME) . '&action=add_table">' . $this->__('Add New Table') . '</a></div>';
        $form .= '<p class="description">The shortcode also accepts an optional parameter, "sortable", which will
				<br>cause the table to be sortable by clicking on the column title.
				<br>Example: [table-maker id="1" sortable="true"]</p>';
        return $form;
    }

    /**
     * fornm for editing a tables settings
     * @return string HTML for the table to be created
     *
     */
    private function edit_table() {
        $tablename = $columns = $colsettings = $class = $tableclass = '';
        $table_id = $this->request('table_id');
        if (isset($_POST['submit'])) {
            $name = $this->request('name');
            if (!$name) {
                $this->set_error($this->__('A name for the table must be provided.'));
                $columns = $this->request('columns');
                $colsettings = json_decode(json_encode($this->request('column')));
            } else {
                $this->save_table($table_id);
                $this->set_message($this->__('Table saved.'));

                return $this->list_tables();
            }
        } else {
            $table = $this->get_table($table_id);
            $name = (!empty($table)) ? $table->tablename : '';
            $columns = (!empty($table)) ? $table->columns : 4;
            $colsettings = (!empty($table)) ? json_decode($table->colsettings) : '';
        }

        $form = '';
        $form .= '<h3>Edit Structure for Table</h3>';
        $form .= '<form method="post" action="' . $this->url . '">';
        $form .= '<div><label for="name">' . $this->__('Table Name') . '</label><input name="name" value="' . esc_attr($name) . '" /></div>';
        $form .= '<div><label for="columns">' . $this->__('Number of Columns') . '</label>';
        $form .= $this->build_dropdown('columns', $this->dropdown_count(), $columns, '', 'columns');
        $form .= '</div>';
        $form .= '<div>';
        $form .= '<p class="description">' . $this->__('Define the names of your columns below. Optionally, you can also specify a CSS class for each column.') . '</p>';
        $form .= '<table><thead><tr><th>&nbsp;</th><th>Column Name</th><th>Class</th></tr></thead><tbody>';
        if ($columns) {
            for ($i = 1; $i <= $columns; $i++) {
                $form .= '<tr class="visible">';
                $name = (!empty($colsettings->$i->name)) ? $colsettings->$i->name : '';
                $class = (!empty($colsettings->$i->class)) ? $colsettings->$i->class : '';
                $form .= $this->build_row($i, $name, $class);
                $form .= '</tr>' . PHP_EOL;
            }
        }

        $i = (!empty($i)) ? $i : 1;
        for ($i = $i; $i <= 10; $i++) {
            $form .= '<tr class="hidden">';
            $form .= $this->build_row($i, $tablename, $tableclass);
            $form .= '</tr>' . PHP_EOL;
        }

        $form .= '</tbody></table>';
        $form .= '</div>';
        $form .= <<<SCRIPT
			<script>
				jQuery(function($) {
					$('#columns').change(function() {
						var cols = parseInt($(this).val());
						$('tr').slice(0, cols + 1).show();
						$('tr').slice(cols + 1, 11).hide();
					}).trigger('change');
				});

			</script>
SCRIPT;

        $form .= '<div><input type="submit" class="button button-primary" name="submit" value="' . $this->__('Save Table Structure') . '">
				<a href="' . $this->url . '" class="button">' . $this->__('Cancel') . '</a></div>';
        $form .= wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $form .= '<input type="hidden" name="action" value="edit_table" />';
        $form .= '<input type="hidden" name="table_id" value="' . $table_id . '" />';
        $form .= '</form>';
        return $form;
    }

    /**
     * @param int $i count of rows
     * @param string $name
     * @param string $class
     *
     * @return string
     */
    private function build_row($i, $name, $class) {
        $content = '<td><label for="column[' . $i . '][name]">' . $this->__('Col ' . $i) . '</label></td>
					<td><input name="column[' . $i . '][name]" value="' . $name . '" id="column[' . $i . '][name]"/></td>
					<td><input name="column[' . $i . '][class]" value="' . $class . '" /></td>';

        return $content;
    }

    /**
     * Admin functionality.
     * Save the table title.
     *
     * @param int $table_id
     * @param string $title
     */
    private function save_table($table_id) {
        $name = $_POST['name'];
        $columns = $_POST['columns'];
        $colsettings = $_POST['column'];
        $colsettings = json_encode(array_filter(array_map('array_filter', $colsettings)));
        $table_data = array(
            'tablename' => $name,
            'columns' => $columns,
            'colsettings' => $colsettings
        );

        if ($table_id) {
            $where = array('id' => $table_id);
            $this->db->update($this->table, $table_data, $where);
        } else {
            $this->db->insert($this->table, $table_data);
            $table_id = $this->db->insert_id;
        }

        return $table_id;
    }

    /**
     * @return string Table inside a form for saving data
     */
    private function build_table() {
        $table_id = $this->request('table_id');
        $table_structure = $this->get_table($table_id);
        $columns = $table_structure->columns;
        $table_data = $this->get_table_data($table_id);
        $table = (array)json_decode($table_structure->colsettings);
        $content = '';
        $content .= '<h3>Add / Edit Data for Table <em>' . $table_structure->tablename . '</em></h3>';
        $content .= '<form method="post">';
        $content .= '<input type="submit" class="btn button-primary" value="Save Data">';
        $content .= '<table class="data">';
        $content .= '<thead>';
        $content .= '<tr><th>&nbsp;</th>';
        $header_cells = 1;
        foreach ($table AS $column => $value) {
            if ($header_cells > $columns) {
                break;
            }

            $content .= '<th>' . $value->name . '</th>';
            $header_cells++;
        }

        $content .= '<th>Delete</th></tr>';
        $content .= '</thead>';
        $content .= '<tbody>';
        $count = count($table);

        //builds one blank row
        if (empty($table_data)) {
            $content .= '<tr class="blank"><td class="handle">&equiv;</td>';
            $row = 1;
            $i = 0;
            foreach ($table AS $column => $value) {
                $content .= '<td class=""><input type="text" name="data[' . $row . '][' . $column . ']"></td>';
                $i++;
                if ($i == $count) {
                    $row++;
                }
            }

            $content .= '</tr>';
        } else {
            $table_entries = (array)json_decode($table_data[0]->contents);
            if ($table_entries) {
                foreach ($table_entries as $row => $stuff) {
                    $content .= '<tr>';
                    $content .= '<td class="handle">&equiv;</td>';
                    $cell_count = 1;
                    //count of $stuff is row count, count of $col is col count
                    foreach ($stuff AS $col => $value) {
                        //if the table structure is chagned from more to less columns cut out the older columns by a break in the display
                        $content .= '<td class=""><input type="text" name="data[' . $row . '][' . $col . ']" value="' . esc_attr(stripslashes($value)) . '"></td>';
                        if ($cell_count >= $columns) {
                            break;
                        }

                        $cell_count++;
                    }

                    //If a table was built, a col removed and then added back, structure should allow for new, blank cells
                    if ($col < $columns) {
                        for ($col; $col < $columns; $col++) {
                            $content .= '<td class=""><input type="text" name="data[' . $row . '][' . $col . ']" value=""></td>';
                        }
                    }

                    $content .= '<td class="removerow"><input type="button" value="x" name="remove" class="removerow"></td></tr>';
                }
            }
        }

        $content .= '</tbody>';
        $content .= '</table>';
        $content .= '<div class="addrow">';
        $content .= '<input type="button" class="btn button addrow" value="+ Add Row">';
        $content .= '</div>';
        $content .= <<<SCRIPT
			<script>
				jQuery(function($) {
					var rows = $('table tbody tr').length;
					var cols = $('table tr th').length - 1;
					var clicked = false;
					var row = (clicked == false) ? {$row} +1 : row;

					$('input.addrow').click(function() {
						clicked = true;
						var html;
						html = '<tr><td class="handle">&equiv;</td>';
						for(i = 1; i < cols; i++) {
							html += '<td><input type="text" name="data[' + row + '][' + i + ']"></td>';
						}
						html += '<td class="removerow"><input type="button" value="x" name="remove" class="removerow"></td></tr>'

						$('table tbody').append(html);
						row++;

					});

				});

			</script>
SCRIPT;
        $content .= '<input type="hidden" name="action" value="save_table_data">';
        $content .= '<input type="hidden" name="table_id" value="' . $table_id . '">';
        $content .= '<input type="hidden" name="columns" value="' . $columns . '">';
        $content .= '</form>';

        return $content;
    }

    /**
     * Save data for a table that has the structure built
     */
    private function save_table_data() {
        $table_id = $_POST['table_id'];
        $row = count($_POST['data']);
        $columns = $_POST['columns'];
        $data = $_POST['data'];
        $data = json_encode($data);
        $table_data = array(
            'table_id' => $table_id,
            'col' => $columns,
            'row' => $row,
            'contents' => $data
        );

        if ($table_id) {
            $where = array('table_id' => $table_id);
            $this->db->delete($this->table_data, $where);
            $this->db->insert($this->table_data, $table_data);
            $table_id = $this->db->insert_id;
        }

        if ($this->db->insert_id) {
            $this->set_message('Table Data updated.');
        } else {
            $this->set_error('There was a problem saving table data.');
        }
    }

    /**
     * Delete table
     *
     * @return String Message or Error
     */
    private function delete_table() {
        $table_id = $this->request('table_id');
        $result = $this->delete($table_id);
        if ($result) {
            return $this->set_message('Table Deleted');
        } else {
            return $this->set_error('There was a problem deleting the table');
        }
    }


    /**
     * Returns list of tables from database.
     *
     * @return array
     */
    public function get_tables() {
        return (array)$this->db->get_results('SELECT * FROM ' . $this->table);
    }

    /**
     * Returns a single table record.
     *
     * @param int $table_id
     *
     * @return object
     */
    private function get_table($table_id) {
        return $this->db->get_row($this->db->prepare('SELECT * FROM ' . $this->table . ' WHERE id = %d', $table_id));
    }

    /**
     * Returns results froma a query of the table data table
     *
     * @param $table_id
     *
     * @return object form a query
     */
    private function get_table_data($table_id) {
        return $this->db->get_results($this->db->prepare('SELECT * FROM ' . $this->table_data . ' WHERE table_id = %d', $table_id));
    }

    private function delete($table_id) {
        $this->db->query($this->db->prepare('DELETE FROM ' . $this->table_data . ' WHERE table_id = %d', $table_id));

        return $this->db->query($this->db->prepare('DELETE FROM ' . $this->table . ' WHERE id = %d', $table_id));
    }


    /**
     * Shortcode processor.
     * Loads into output buffer and returns as string for proper placement in WP content.
     *
     * @param array $args
     *
     * @return string
     */
    public function shortcode($args) {
        return $this->do_table($args);
    }

    /**
     * Generate the actual markup for a table.
     *
     * @param array $args
     */
    public function do_table($args) {
        if (empty($args['id'])) {
            return '<div class="error">There is no table id set.<div>';
        }
        $class = (!empty($args['sortable'])) ? ' sortable' : '';
        $table_id = $args['id'];
        $table = $this->get_table($table_id);
        $data = $this->get_table_data($table_id);
        $content = '<table class="tablemaker' . $class . '">';
        $content .= '<thead>';
        $content .= '<tr>';
        $colsettings = json_decode($table->colsettings);
        $row = 1;
        foreach ($colsettings AS $column => $value) {
            if ($row > (int)$table->columns) {
                break;
            }
            $class = (!empty($value->class)) ? 'class="' . $value->class . '"' : '';
            $content .= '<th ' . $class . '>' . $value->name . '</th>';
            $row++;

        }

        $content .= '</tr>';
        $content .= '</thead>';
        $content .= '<tbody>';
        $table_entries = (array)json_decode($data[0]->contents);
        if ($table_entries) {
            foreach ($table_entries as $row => $stuff) {
                $row_count = 1;
                $content .= '<tr>';
                foreach ($stuff AS $col => $value) {
                    if ($row_count > $table->columns) {
                        break;
                    }

                    $class = (!empty($colsettings->$col->class)) ? $colsettings->$col->class : '';
                    $content .= '<td class="' . $class . '">' . stripslashes($value) . '</td>';
                    $row_count++;
                }

                $content .= '</tr>';
            }
        }

        $content .= '</tbody>';
        $content .= '</table>';

        $this->shortcode_run = TRUE;
        return $content;
    }

    /**
     * Utility method for setting / collecting error messages
     *
     * @param $string
     */
    private function set_error($string) {
        self::$error[] = $string;
    }

    /**
     * Utility method for setting / collecting success / update messages
     *
     * @param $string
     */
    private function set_message($string) {
        self::$message[] = $string;
    }

    /**
     * Utility method to determine if there are any errors set
     * @return bool
     */
    protected function has_errors() {
        return (self::$error) ? TRUE : FALSE;
    }

    /**
     * Utility method for outputting errors.
     */
    protected function output_errors() {
        if (!self::$error) {
            return;
        }

        $error = self::$error;
        if (is_array($error)) {
            $error = implode('<br>', self::$error);
        }

        echo '<div class="error"><p>' . $error . '</p></div>';
    }

    protected function output_messages() {
        if (!self::$message) {
            return;
        }

        $message = self::$message;
        if (is_array($message)) {
            $message = implode('<br>', self::$message);
        }

        echo '<div class="updated"><p>' . $message . '</p></div>';
    }

    /**
     * Wrapper around the WP __ function for convenience
     *
     * @param $string
     *
     * @return string|void
     */
    protected function __($string) {
        return __($string, self::LANG);
    }

    /**
     * Wrapper around the WP _e function for convenience
     *
     * @param $string
     *
     * @return string|void
     */
    protected function _e($string) {
        echo $this->__($string);
    }

    /**
     * Update the DB as well as the class version variable
     *
     * @param float $version
     */
    private function update_version($version) {
        update_option(self::VERSION_KEY, $version);
        $this->version = $version;
    }

    /**
     * Safer / more convenient than $_GET / $_POST
     * Excludes $_REQUEST intentionally
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    private function request($key, $default = NULL) {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }

        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        return $default;
    }

    /**
     * @param int $max number of times ot loop
     *
     * @return array of numbers for looping over
     */
    private function dropdown_count($max = 10) {
        $array = array();
        for ($i = 1; $i <= $max; $i++) {
            $array[$i] = $i;
        }

        return $array;
    }

    private function build_dropdown($name, $select_array, $selected, $class = '', $id = '') {
        $content = '<select name="' . $name . '" class="' . $class . '" id="' . $id . '">' . PHP_EOL;;
        foreach ($select_array as $key => $value) {
            $content .= '<option value="' . $value . '"';
            $content .= ($value == $selected) ? ' selected' : '';
            $content .= '>' . $value . '</option>' . PHP_EOL;
        }

        $content .= '</select>' . PHP_EOL;
        return $content;
    }

    /**
     * Admin functioanality to check and ensure proper tables exist for the table.
     */
    private function acg_plugin_tablemaker_checktables() {
        if ($this->version < 1) {
            if ($this->db->get_var("SHOW TABLES LIKE '" . $this->table . "'") != $this->table) {
                $sql = "CREATE TABLE " . $this->table . " (
	                        id INT(11) NOT NULL AUTO_INCREMENT,
							tablename VARCHAR(255) NOT NULL,
							columns TINYINT(4) NOT NULL,
							colsettings TEXT NOT NULL,
	                        PRIMARY KEY (id))";
                $this->db->query($sql);
            }

            if ($this->db->get_var("SHOW TABLES LIKE '" . $this->table_data . "'") != $this->table_data) {
                $sql = "CREATE TABLE " . $this->table_data . " (
	                        id INT(11) NOT NULL AUTO_INCREMENT,
	                        table_id INT(11) NOT NULL,
							row TINYINT(4) UNSIGNED NULL,
							col TINYINT(4) UNSIGNED NULL,
							contents TEXT NULL,
	                        PRIMARY KEY (id))";
                $this->db->query($sql);
            }

            $this->update_version(1);
        }
    }

}

$acg_hps = new ACGTablemaker();
