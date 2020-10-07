<?php

/***
 * Fix mfa.declarebusinessgroup.ga
 * 
 * IMPORTANT: Set the wpmodel in application's folder
 * 
 * The script will extract the necessary data from the infected site and will transfer it to the new website
 * - Plugins, Themes, uploads will be exported
 * 
 * Usage php fix_declarebusinessgroup.php [infected_folder] [cleanup_destination] [url]
 * 
 */


 if (!isset($argv[1]) || !isset($argv[2]) || !isset($argv[3]) ) {
     
     die ('Please set the arguments. Usage: "php fix_declarebusinessgroup.php [infected_folder] [destination] [url_no_protocol]"');
 }

 MBAFixer::getInstance($argv[1], $argv[2], $argv[3]);



 class MBAFixer {

    private static $me;

    private $infected_wp;
    private $cleanup_wp;
    private $model_wp = '/wpmodel/';

    private $regex_define = "/(define\\([ ]?[\"']::var::['\"],[ ]{0,}[\"'])([^'\"]+[\"'])/";
    private $regex_prefix ='/(\$table_prefix[ ]{0,}=[ ]{0,}[\'"])([^\'"]+)/';

    private $url;
    private $db; // MySQL instance
    private $config; // wp configuration

    private $protocol = 'https';

    public static function getInstance($from, $to, $url) {
        if ( empty(self::$me) ) {
            self::$me = new self($from, $to, $url);
        }
        return self::$me;
    }

    /**
     * Main execution
     */
    protected function __construct($from, $to, $url) {
        
        // 0) Set variables
        $this->infected_wp = $from;
        $this->url = $url;
        $domain = $this->getDomain($this->url);
        $this->cleanup_wp = "$to/$domain/";
        
        $this->config = $this->getConfiguration(); // Wp config parameters

        // Connect to database
        $this->db = new mysqli($this->config['defines']['DB_HOST'], $this->config['defines']['DB_USER'], $this->config['defines']['DB_PASSWORD'], $this->config['defines']['DB_NAME']);

        // 1) Copy empty model to destination
        $path = dirname(__FILE__) . $this->model_wp;
        $cmd = "cp -r {$path} {$this->cleanup_wp}";
        $this->exec($cmd);

        // 2) Copy uploads from infected to destination
        $this->exec("cp -r {$this->infected_wp}/wp-content/uploads {$this->cleanup_wp}/wp-content/");
        $this->exec("cp -r {$this->infected_wp}/wp-content/themes {$this->cleanup_wp}/wp-content/");
        $this->exec("cp -r {$this->infected_wp}/wp-content/plugins {$this->cleanup_wp}/wp-content/");

        // 3) Generate new wp-config
        $this->createWPConfigFile();

        // 4) Scan cleaned folder to remove any remaining dangerous files
        $this->removeAllDangerousFiles($this->cleanup_wp);

        // 5) Cleanup Database
        $this->cleanupMySql();
        

    }

    protected function exec($shell_cmd) {
        echo "Shell exec: {$shell_cmd}\n";
        exec($shell_cmd);
    }

    protected function getDomain($url) {
        $exp = explode('.', $url);
        if (count($exp) == 3) {
            return "{$exp[1]}.{$exp[2]}";
        } elseif (count($exp) == 2) {
            return "{$exp[0]}.{$exp[1]}";
        }
        return null;
    }

    protected function getConfiguration() {
        $file_path = $this->infected_wp . '/wp-config.php';
        $wp_config = file_get_contents($file_path);

        $constants = [
            'DB_NAME',
            'DB_USER',
            'DB_PASSWORD',
            'DB_HOST',
            'DB_CHARSET',
            'DB_COLLATE',

            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT',

        ]; 

        $out_const = [];
        foreach ($constants as $c) {
            
            preg_match(str_replace("::var::", $c, $this->regex_define), $wp_config, $matches);
            if (empty($matches[2])) {
                echo "Could not find definition of {$c}, please set manualy or ignore\n";
                continue;
            }
            $out_const[$c] = substr($matches[2], 0, -1);
        }

        // 2) get table prefix
        
        preg_match($this->regex_prefix, $wp_config, $matches);
        $table_prefix = $matches[2];
        
        return [
            'defines' => $out_const,
            'table_prefix' => $table_prefix,
        ];


    }


    public function createWPConfigFile() {

        // 0) Load config from infected website
        $config = $this->config;

        // 1) Take sample config and replace defines (mysql connection)
        $wp_config = file_get_contents("$this->infected_wp/wp-config-sample.php");
        foreach ($config['defines'] as $c => $val) {
            $escaped_val = str_replace(['$'], ['\$'], $val);
            $wp_config = preg_replace(str_replace("::var::", $c, $this->regex_define), '${1}' . $escaped_val . "'", $wp_config);
        }

        // 2) Update prefix
        $wp_config = preg_replace($this->regex_prefix, '${1}' . $config['table_prefix'], $wp_config);

        // 3) Create new wp-config file in clean version
        file_put_contents("$this->cleanup_wp/wp-config.php", $wp_config);

    }

    protected function cleanupMySql() {

        // 0) Define search and replaces
        $search = [
            '<script src=\'https://mfa.declarebusinessgroup.ga/m.js?n=ns1\' type=\'text/javascript\'></script>',
            'https://mfa.declarebusinessgroup.ga/det.php?sit=follow&sid=3&yuid=1&',
            'https://mfa.declarebusinessgroup.ga/det.php?sit=follow&sid=2&yuid=1&',
            'https://mfa.declarebusinessgroup.ga/det.php?sit=follow&amp;sid=2&amp;yuid=1&amp;',
            'https://mfa.declarebusinessgroup.ga/det.php?sit=follow&amp;sid=3&amp;yuid=1&amp;',
        ];

        $replacement = []; // we create empty strings (same amount as search)
        foreach ($search as $row) { 
            $replacement[] = '';
        }

        // 1) Get tables to search and replace for parasit
        $tables_to_clean = $this->getTablesToCleanup();
       
        
        // 2) Fix options tables())
        $this->fixOptions($tables_to_clean['options'], $search, $replacement);

        // 3) Fix posts table(s)
       $this->fixPosts($tables_to_clean['posts'], $search);


    }

    protected function isMultisite() {
        $res = $this->db->query("SHOW TABLES LIKE '{$this->config['table_prefix']}blogs'");
        if (!$res) {
            return false;
        } elseif ($res->fetch_assoc() != false) {
            return true;
        }
        return false;
        
    }

    /**
     * Get wp_posts an wp_options (multisite / none-multisite)
     */
    protected function getTablesToCleanup() {
         // Loading default website (valid both on multisite and none-multisite)
         $posts = [
            $this->config['table_prefix'] . 'posts'
        ];

        // Options to replace by table (only 1 in this case)
        $options[$this->config['table_prefix'] . 'options'] = [
            'siteurl' => "{$this->protocol}://{$this->url}",
            'home' => "{$this->protocol}://{$this->url}",
        ];

        // If multisite then we will need to check also in other sub site tables
        if ($this->isMultisite()) {

            echo "MULTISITE DETECTED\n";
            $blogs = $this->db->query("SELECT * FROM {$this->config['table_prefix']}blogs WHERE blog_id != 1");
            while ( $b = $blogs->fetch_assoc() ) {

                // Tables to search and replace for parasit
                $posts[] = $this->config['table_prefix'] . $b['blog_id'] . '_posts';

                // Options to replace by table (1 per subsite)
                $options[$this->config['table_prefix'] . $b['blog_id'] . '_options'] = [
                    'siteurl' => "{$this->protocol}://{$b['domain']}{$b['path']}",
                    'home'  => "{$this->protocol}://{$b['domain']}{$b['path']}",
                ];
            }
        } 

        return [
            'posts' => $posts,
            'options' => $options,
        ];
    }


    /***
     * Fix wp_posts
     */
    public function fixPosts($posts, $search) {

        foreach ($posts as $ptable) {
            foreach ($search as $s) {
                $s = addslashes($s);
                foreach (['post_content', 'post_title'] as $field) {
                    $sql = 'UPDATE '.$ptable.' SET '.$field.' = (REPLACE ('.$field.', "'.$s.'", ""))';
                    $this->db->query($sql);
                    if ($this->db->affected_rows > 0) {
                        echo "==> Cleaned up {$ptable}.{$field} - Replaced " . (int)$this->db->affected_rows . " row(s)\n";
                    } else {
                        echo "==> Checked {$ptable}.{$field} - OK\n";
                    }
                    
                }
               
            }
        }
    }

    /***
     * Fix wp_options
     */
    protected function fixOptions($options, $search, $replacement) {
        
        foreach ($options as $option_table => $fields) {

            // Fix siteurl & blog_url 
            foreach ($fields as $name => $val) {
                $sql = "UPDATE {$option_table} SET option_value = '{$val}' WHERE option_name = '{$name}'";
                $this->db->query($sql);
            }

            // Fix serialized data in options - the virus gets in serialized data too, such a mess !!! But I am coming for you buddy!! 
            $res = $this->db->query("SELECT * FROM {$option_table}");
            while ( $row = $res->fetch_assoc() ) {

                $data = @unserialize($row['option_value']);

                $is_obj = false;
                
                if ($data === false) { // Non serialized data
                    
                    $data = $row['option_value']; // We replace data with the string
                    $cleaned_str = str_replace($search, $replacement, $data);

                    if ($cleaned_str != $data) { // we save only if changed
                        echo "\tREPLACED OPTION DATA {$row['option_name']} (no serialization)\n";
                        $this->db->query("UPDATE {$option_table} SET option_value = '" . addslashes($cleaned_str) . "' WHERE option_name = '{$row['option_name']}'");
                    }
                    continue; 
                }

                // If we reach here, then serialized data - we replace any instances found, then save it back as serialized

                $is_obj = is_object($data);
                // Convert stdclass to array
                if ($is_obj || is_array($data)) { // Sometimes array contain objects too, so better decode then reencode them
                    $data = json_decode(json_encode($data), true);
                }

                // Cleanup
                $cleaned_data = $data; // we duplicate array
                array_walk_recursive($cleaned_data, function (&$val, $key) USE ($search, $replacement, $is_obj, $data) {
                    $val = str_replace($search, $replacement, $val); // replace the fixed value
                });

                if ($cleaned_data != $data) { // we save only if changed
                    echo "\tREPLACED OPTION SERIALIZED DATA {$row['option_name']}\n";
                    $cleaned_data = $is_obj ? json_decode(json_encode($cleaned_data), false) : $cleaned_data;
                    $this->db->query("UPDATE {$option_table} SET option_value = '" . addslashes( serialize($cleaned_data) ) . "' WHERE option_name = '{$row['option_name']}'");
                }
                
                


            }
        }
    }


    protected function removeAllDangerousFiles($path) {
        $files_to_remove = [
            'wpconfig.bak.php'
        ];

        $it = new RecursiveDirectoryIterator($path);
        
        foreach(new RecursiveIteratorIterator($it) as $file) {
            foreach ($files_to_remove as $f) {
                if(strrpos($file, $f) !== false) {
                echo "DELETING: {$file}\n";
                    unlink($file);
                }
            }
            
        }
    }
 }


