#!/usr/bin/php -q
<?php
if($argc < 9)
    exit("Incorrect syntax : bakeModels.php sgbd host database username password schema folder singularise(Y/N)");
$sgbd = $argv[1];
$host = $argv[2];
$database = $argv[3];
$username = $argv[4];
$password = $argv[5];
$schema = $argv[6];
$folder = $argv[7];
$singularise = $argv[8];

$connexion = new PDO("$sgbd:host=$host;dbname=$database", "$username", "$password");

function getTables($connexion, $schema) {
    switch($connexion->getAttribute(constant("PDO::ATTR_DRIVER_NAME"))) {
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

function createFiles($connexion, $tableNames, $folder, $singularise) {
    foreach ($tableNames as $table) {        
        $primaryKey = getPrimaryKey($connexion, $table[0]);
        if($singularise == 'Y')
            $modelName = ucfirst(singularize($table[0]));
        else
            $modelName = ucfirst($table[0]);        
        if (strpos($modelName, "_") >= 0) {
            $parts = explode("_", $modelName);
            foreach ($parts as $key => $value) {
                $parts[$key] = ucfirst($value);
            }
            $modelName = implode("", $parts);
        }
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

/**
* @author Bermi Ferrer Martinez 
* @copyright Copyright (c) 2002-2006, Akelos Media, S.L. http://www.akelos.org
* @license GNU Lesser General Public License 
* @since 0.1
* @version $Revision 0.1 $
*/
function singularize($word)
{
    $singular = array (
    '/(quiz)zes$/i' => '\1',
    '/(matr)ices$/i' => '\1ix',
    '/(vert|ind)ices$/i' => '\1ex',
    '/^(ox)en/i' => '\1',
    '/(alias|status)es$/i' => '\1',
    '/([octop|vir])i$/i' => '\1us',
    '/(cris|ax|test)es$/i' => '\1is',
    '/(shoe)s$/i' => '\1',
    '/(o)es$/i' => '\1',
    '/(bus)es$/i' => '\1',
    '/([m|l])ice$/i' => '\1ouse',
    '/(x|ch|ss|sh)es$/i' => '\1',
    '/(m)ovies$/i' => '\1ovie',
    '/(s)eries$/i' => '\1eries',
    '/([^aeiouy]|qu)ies$/i' => '\1y',
    '/([lr])ves$/i' => '\1f',
    '/(tive)s$/i' => '\1',
    '/(hive)s$/i' => '\1',
    '/([^f])ves$/i' => '\1fe',
    '/(^analy)ses$/i' => '\1sis',
    '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\1\2sis',
    '/([ti])a$/i' => '\1um',
    '/(n)ews$/i' => '\1ews',
    '/s$/i' => '',
    );

    $uncountable = array('equipment', 'information', 'rice', 'money', 'species', 'series', 'fish', 'sheep');

    $irregular = array(
    'person' => 'people',
    'man' => 'men',
    'child' => 'children',
    'sex' => 'sexes',
    'move' => 'moves');

    $lowercased_word = strtolower($word);
    foreach ($uncountable as $_uncountable){
        if(substr($lowercased_word,(-1*strlen($_uncountable))) == $_uncountable){
            return $word;
        }
    }

    foreach ($irregular as $_plural=> $_singular){
        if (preg_match('/('.$_singular.')$/i', $word, $arr)) {
            return preg_replace('/('.$_singular.')$/i', substr($arr[0],0,1).substr($_plural,1), $word);
        }
    }

    foreach ($singular as $rule => $replacement) {
        if (preg_match($rule, $word)) {
            return preg_replace($rule, $replacement, $word);
        }
    }

    return $word;
}

createFiles($connexion, getTables($connexion, $schema), $folder, $singularise);
?>
