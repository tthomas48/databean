<?php

class BuilderTask extends Task {

	protected $file;    // the source file (from xml attribute)
	protected $filesets = array(); // all fileset objects assigned to this task
	protected $url;
	protected $userid;
	protected $password;
	protected $outputdir;
	protected $dbh;

	/**
	 * Nested creator, creates a FileSet for this task
	 *
	 * @return FileSet The created fileset object
	 */
	public function createFileSet() {
		$num = array_push($this->filesets, new FileSet());
		return $this->filesets[$num-1];
	}

	/**
	 * File to be performed syntax check on
	 * @param PhingFile $file
	 */
	public function setFile(PhingFile $file) {
		$this->file = $file;
	}
	
	public function setUrl($url) {
		$this->url = $url;
	}
	
	public function setUserid($userid) {
		$this->userid = $userid;
	}
	
	public function setPassword($password) {
		$this->password = $password;
	}
	
	public function setOutputdir($outputdir) {
		$this->outputdir = $outputdir;
		@mkdir($this->outputdir);
	}
	
	/**
	 * The init method: Do init steps.
	 */
	public function init() {
    
	}

	/**
	 * The main entry point method.
	 */
	public function main() {
		if(!isset($this->file) and count($this->filesets) == 0) {
			throw new BuildException("Missing either a nested fileset or attribute 'file' set");
		}
		
		$this->dbh = new PDO($this->url, $this->userid, $this->password);

		if($this->file instanceof PhingFile) {
			$this->load($this->file->getPath());
		} else { // process filesets
			$project = $this->getProject();
			foreach($this->filesets as $fs) {
				$ds = $fs->getDirectoryScanner($project);
				$files = $ds->getIncludedFiles();
				$dir = $fs->getDir($this->project)->getPath();
				foreach($files as $file) {
					$this->load($dir.DIRECTORY_SEPARATOR.$file);
				}
			}
		}
		$this->build();
	}
	
	private function load($path) {
		require_once($path);
		
	}
	private function build() {
		
		
		$classes = get_declared_classes();
		foreach($classes as $class) {
			$rc = new ReflectionClass($class);
			if($rc->isSubclassOf("\BuyPlayTix\DataBean\DataBean")) {
				if($rc->hasProperty("table")) {
					$instance = $rc->newInstance();
					$table_property = $rc->getProperty("table");
					$table_property->setAccessible(true);
					$this->processTable($class, $table_property->getValue($instance));
				}
			}
		}
	}
	
	private function processTable($class, $table) {
		$fields = array();
		foreach($this->dbh->query('desc ' . $table, PDO::FETCH_ASSOC) as $row) {
			$fields[] = $row;
		}
		$output = '<?p' . "hp\n";
		$output .= "$class::\$field_defs = " . var_export($fields, true) . ";\n";
		
		$path = $this->outputdir . strtolower($table) . ".php";
		file_put_contents($path, $output);
	}
}