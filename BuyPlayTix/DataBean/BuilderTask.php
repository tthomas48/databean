<?php

class BuilderTask extends Task
{

    protected $file;
 // the source file (from xml attribute)
    protected $filesets = array();
 // all fileset objects assigned to this task
    protected $url;

    protected $userid;

    protected $password;

    protected $outputdir;

    protected $dbname;

    protected $dbhost;

    protected $classpath;

    /**
     * Nested creator, creates a FileSet for this task
     *
     * @return FileSet The created fileset object
     */
    public function createFileSet()
    {
        $num = array_push($this->filesets, new FileSet());
        return $this->filesets[$num - 1];
    }

    /**
     * File to be performed syntax check on
     * 
     * @param PhingFile $file            
     */
    public function setFile(PhingFile $file)
    {
        $this->file = $file;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setUserid($userid)
    {
        $this->userid = $userid;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function setOutputdir($outputdir)
    {
        $this->outputdir = $outputdir;
        @mkdir($this->outputdir);
    }

    public function setClasspath($classpath)
    {
        $this->classpath = $classpath;
    }

    /**
     * The init method: Do init steps.
     */
    public function init()
    {}

    /**
     * The main entry point method.
     */
    public function main()
    {
        if (! isset($this->file) and count($this->filesets) == 0) {
            throw new BuildException("Missing either a nested fileset or attribute 'file' set");
        }
        
        // init the database
        \BuyPlayTix\DataBean\DB::init(array(
            "user" => $this->userid,
            "pass" => $this->password,
            "dsn" => $this->url
        ));
        
        $this->dbh = new PDO($this->url, $this->userid, $this->password);
        
        if ($this->file instanceof PhingFile) {
            $this->load($this->file->getPath());
        } else { // process filesets
            $project = $this->getProject();
            foreach ($this->filesets as $fs) {
                $ds = $fs->getDirectoryScanner($project);
                $files = $ds->getIncludedFiles();
                $dir = $fs->getDir($this->project)->getPath();
                foreach ($files as $file) {
                    $this->load($dir . DIRECTORY_SEPARATOR . $file);
                }
            }
        }
        $this->build();
    }

    private function load($path)
    {
        require_once ($path);
    }

    private function build()
    {
        if (! empty($this->classpath)) {
            ini_set("include_path", $this->classpath);
        }
        
        $classes = get_declared_classes();
        foreach ($classes as $class) {
            $rc = new ReflectionClass($class);
            if ($rc->isSubclassOf("\BuyPlayTix\DataBean\DataBean")) {
                if ($rc->hasProperty("table")) {
                    $instance = $rc->newInstance();
                    $table_property = $rc->getProperty("table");
                    $table_property->setAccessible(true);
                    $table_name = $table_property->getValue($instance);
                    if (! empty($table_name)) {
                        $this->processTable($class, $table_property->getValue($instance));
                    }
                }
            }
        }
    }

    private function processTable($class, $table)
    {
        $fields = array();
        foreach ($this->dbh->query('desc ' . $table, PDO::FETCH_ASSOC) as $row) {
            $fields[] = $row;
        }
        $output = '<?p' . "hp\n";
        $output .= "namespace BuyPlayTix\DataBean\Builder;\n";
        $output .= "trait " . strtolower($table) . "_trait { \n\n";
        
        $output .= "public static \$field_defs = [\n";
        foreach ($fields as $field) {
            $val = [];
            $val['db-type'] = $field["Type"];
            $val['type'] = 'unknown';
            if ($field["Field"] === 'UID') {
                $val['type'] = 'pk';
            } elseif (strpos($field["Field"], '_UID') !== false) {
                $val['type'] = 'fk';
                $val['join'] = str_replace('_UID', '', $field["Field"]) . "S";
            } elseif (strpos($val['db-type'], 'varchar') !== false || strpos($val['db-type'], 'char') !== false) {
                $val['type'] = 'text';
            } elseif (strpos($val['db-type'], 'text') !== false) {
                $val['type'] = 'text';
            } elseif (strpos($val['db-type'], 'decimal') !== false || strpos($val['db-type'], 'int') !== false) {
                $val['type'] = 'number';
            } elseif (strpos($val['db-type'], 'enum') !== false) {
                $val['type'] = 'enum';
                $val['values'] = explode('\',\'', str_replace('enum(\'', '', str_replace('\')', '', $val['db-type'])));
            } elseif (strpos($val['db-type'], 'blob') !== false) {
                $val['type'] = 'unsupported';
            } elseif (strpos($val['db-type'], 'timestamp') !== false || strpos($val['db-type'], 'date') !== false) {
                $val['type'] = 'timestamp';
            }
            
            $output .= "'" . $field["Field"] . "' => " . var_export($val, true) . ",\n";
        }
        $output .= "];\n";
        
        foreach ($fields as $field) {
            $output .= "    public function get_" . strtolower($field["Field"]) . "() {\n";
            $output .= "        if (array_key_exists(\"" . $field["Field"] . "\", \$this->fields)) {\n";
            $output .= "            return \$this->fields[\"" . $field["Field"] . "\"];\n";
            $output .= "        }\n";
            $output .= "        return null;\n";
            $output .= "    }\n\n";
            
            $output .= "    public function set_" . strtolower($field["Field"]) . "(\$v) {\n";
            $output .= "        \$this->fields[\"" . $field["Field"] . "\"] = \$v;\n";
            $output .= "    }\n\n";
        }
        $output .= "}\n";
        
        $path = $this->outputdir . strtolower($table) . "_trait.php";
        file_put_contents($path, $output);
    }
}
