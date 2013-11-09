#!/usr/bin/php -q
<?php
include 'Inflector.class.php';
echo "SGBD : ";
$sgbd = trim(fgets(STDIN));
if(!in_array($sgbd, array("pgsql", "mysql")))
    die("SGBD unsupported");
echo "Host (localhost): ";
$host = trim(fgets(STDIN));
if(empty($host))
    $host = "localhost";
echo "Database : ";
$database = trim(fgets(STDIN));
echo "Username : ";
$username = trim(fgets(STDIN));
echo "Password : ";
$password = trim(fgets(STDIN));

try {
    $connexion = new PDO("$sgbd:host=$host;dbname=$database", "$username", "$password");
}
catch(Exception $e) {
    die("Erreur : ".$e->getMessage());
}

function getTables($connexion) {
    echo "Schema : ";
    $schema = trim(fgets(STDIN));
    switch($connexion->getAttribute(PDO::ATTR_DRIVER_NAME)) {
        case "pgsql":
            $q = $connexion->prepare("SELECT tablename FROM pg_tables WHERE schemaname = :schema");
            $q->bindValue(":schema", $schema);
            break;
        case "mysql":
            $q = $connexion->query("SHOW TABLES FROM $schema");
            break;
    }    
    $q->execute();
    return $q->fetchAll();
}

function getPrimaryKey($connexion, $tableName) {
    switch($connexion->getAttribute(constant("PDO::ATTR_DRIVER_NAME"))) {
        case "pgsql":
            $q = $connexion->prepare("SELECT c.column_name FROM information_schema.table_constraints tc JOIN information_schema.constraint_column_usage AS ccu USING (constraint_schema, constraint_name) JOIN information_schema.columns AS c ON c.table_schema = tc.constraint_schema AND tc.table_name = c.table_name AND ccu.column_name = c.column_name where constraint_type = 'PRIMARY KEY' and tc.table_name = :tableName");
            $q->bindValue(":tableName", $tableName);
            $q->execute();
            $results = $q->fetch();
            $primaryKey = $results[0];
            break;
        case "mysql":
            $q = $connexion->query("SHOW KEYS FROM `$tableName` WHERE Key_name = 'PRIMARY'");
            $results = $q->fetch();
            $primaryKey = $results[4];
            break;
    }    
    
    return $primaryKey;
}

function createFiles($connexion, $tableNames) {
    echo "Singularise : ";
    $singularise = trim(fgets(STDIN));
    echo "Folder : ";
    $folder = trim(fgets(STDIN));
    echo "Override existing files ? (N) : ";
    $override = trim(fgets(STDIN));
    if(empty($override))
        $override = 'N';
    foreach ($tableNames as $table) {        
        $primaryKey = getPrimaryKey($connexion, $table[0]);
        
        if($singularise == 'Y')
            $modelName = ucfirst(Inflector::singularize($table[0]));
        else
            $modelName = ucfirst($table[0]);      
        
        if (strpos($modelName, "_") >= 0) {
            $parts = explode("_", $modelName);
            foreach ($parts as $key => $value) {
                $parts[$key] = ucfirst($value);
            }
            $modelName = implode("", $parts);
        }
        if($override == 'N' && !file_exists($folder."/" . $modelName . ".php")) {                
            $content = "<?php\r\n";
            $content .= "class ".$modelName." extends AppModel {\r\n";
            $content .= '   public $useTable = "'.$table[0].'";'."\r\n";
            $content .= '   public $primaryKey = "'.$primaryKey.'";'."\r\n";
            $content .= '   public $hasOne = array();'."\r\n";
            $content .= '   public $hasMany = array();'."\r\n";
            $content .= '   public $belongsTo = array();'."\r\n";
            $content .= '   public $hasAndBelongsToMany = array();'."\r\n";
            $content .= '}'."\r\n";
            $content .= '?>';
            file_put_contents($folder."/" . $modelName . ".php", $content);
        }
    }
}

createFiles($connexion, getTables($connexion));
?>
