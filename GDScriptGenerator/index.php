<?php
/* This was made to automate some code generation for GDScript.
 * The idea being you can have a script be generated with certain features removed from the source rather than ignored during runtime.
 * As GDScript is a kinda slow and interpreted language, this will hopefully improve runtime without sacrifing optional functionality.
 * WIP/TODO A lot.
//*/
//Validate script environment
$SCRIPT_VERSION_REQUIREMENTS = array(
	"Core" => array("min"=>"8"), // Version 8+ allows for mixed type unions / parameter type hinting.
);
//No type-hinting in this function is allowed for PHP Core versions older than Major Release 5.
function validate_script_requirements($requirements)
{
	//Returns true if the current PHP environment supports requirements.
	//Returns false otherwise.
	//Unlike version_compare() works before version 4.1.0(but after 4.0.0) and supports more options(but slower(?)).
	//get_loaded_extensions(), foreach, in_array(), explode(), trim(),
	// phpversion(), count(), isset(), is_numeric(), and intval()
	// were all available since at least Core Major Release 4. Hopefully good enough.
	$ext = get_loaded_extensions();
	foreach($requirements as $key => $value)
	{
		//Validate extension presence.
		if(!in_array($key, $ext))
			return false;
		$current_version = explode('.', trim(phpversion($key)));
		$current_version_count = count($current_version);
		//Pre-process/Validate current extension's version.
		for($c = 0; $c < $current_version_count; $c++)
		{
			$current_version[$c] = trim($current_version[$c]);
			if(!is_numeric($current_version[$c]))
				return false;
		}
		//Validate minimum.
		if(isset($value["min"]))
		{
			$minimum_version = explode('.', trim($value["min"]));
			$minimum_version_count = count($minimum_version);
			for($m = 0; $m < $minimum_version_count; $m++)
			{
				if($m >= $current_version_count)
					return false;
				$minimum_version[$m] = trim($minimum_version[$m]);
				if(!is_numeric($minimum_version[$m]))
					return false;
				if(intval($current_version[$m]) < intval($minimum_version[$m]))
					return false;
			}
		}
		//Validate maximum.
		if(isset($value["max"]))
		{
			$maximum_version = explode('.', trim($value["max"]));
			$maximum_version_count = count($maximum_version);
			for($m = 0; $m < $maximum_version_count; $m++)
			{
				if($m >= $current_version_count)
					return false;
				$maximum_version[$m] = trim($maximum_version[$m]);
				if(!is_numeric($maximum_version[$m]))
					return false;
				if(intval($current_version[$m]) > intval($maximum_version[$m]))
					return false;
			}
		}
	}
	return true;
}
if(!validate_script_requirements($SCRIPT_VERSION_REQUIREMENTS))
{
	// Handle unsupported environment.
	// For this case we will just die. R.I.P.
	die("Internal Server Error, contact administrator.");
}

//Debugging/Logging Function(s)
//!TODO Add option to debug to file(?)
$debug_mode = false;
function printd(string $s, string $prefix="[DEBUG]: ", string $suffix="\n") : bool
{
	//Prints s only if debug_mode is enabled.
	//Returns global debug_mode value.
	global $debug_mode;
	if($debug_mode)
	{
		print($prefix . $s . $suffix);
	}
	return $debug_mode;
}
if($debug_mode)
{
	// Show all errors
	error_reporting(E_ALL);
}

//String Function(s)
function implode_r(string $glue, $a, bool $reverse = false)
{
	//Returns string values of elements of a recursively joined by glue.
	if(is_string($a))
		return $a;
	if(is_array($a))
	{
		//Handle Array
		$a_count = count($a);
		if($a_count <= 0)
			return "";
		if($reverse)
			$a = array_reverse($a);
		$buffer = "";
		foreach($a as $e)
		{
			$buffer .= implode_r($glue, $e, $reverse);
			$buffer .= $glue;
		}
		return substr($buffer, 0, strlen($buffer) - strlen($glue));
	}
	return strval($a);
}

//JSON Function(s)
function load_json( string $file_path )
{
	//Attempts to read json from file, decode, and then return value.
	// Read the JSON file.
	$json = file_get_contents("" . $file_path);
	if($json == false)
	{
		printd("load_json() Failed to read script file into memory.");
		return null;
	} else {
		printd("load_json() Finished reading in the raw JSON file.");
	}
	// Decode the JSON file
	return(json_decode($json, true, 512));
}

//Script Specific Stuff
//Global Variables
$DEFAULT_OPTIONS = array(
	"global_defaults"=>array(
		"prefer_multiline"  => true,
		"comment_char"      => '#',
		"comments_char"     => "\"\"\"",
		"end_line"          => "\n",
	),
	"constant_prefix"       => "DEFAULT_",
	"constant_suffix"       => "",
	"export_variable"       => false,
	"generate_constant"     => false,
	"print_header_constants"=> true,
	"print_header_variables"=> true,
	"print_header_setgets"  => true, // Unused in Godot Version 4+
	"print_header_functions"=> true,
	"print_header_methods"  => true,
	"print_header_events"   => true,
	"header_constants"      => "Script Constant(s) / Default(s)",
	"header_variables"      => "Script Variable(s) / Exported Variable(s)",
	"header_setgets"        => "Script setget/set/get Function(s)", // Unused in Godot Version 4+
	"header_functions"      => "Script Function(s)",
	"header_methods"        => "Script Method(s)",
	"header_events"         => "Script Event(s)",
); // Default values can be replaced via json settings.

$default_prefix = "DEFAULT_";//Replace me with $defaults["constant_prefix"]
$json = "";
$json_data = array();

//!TODO
//The meaning of these flags are/should be script/json specific?
$script_mode           = 0b0000000000000000;//uint16
$FLAG_DISABLE_ALL      = 0b1111111111111111;
$FLAG_DISABLE_DEBUG    = 0b0000000000000001;
$FLAG_DISABLE_ROTATION = 0b0000000000000010;
$FLAG_DISABLE_MOTION   = 0b0000000000000100;
$FLAG_DISABLE_COLLISION= 0b0000000000001000;
$FLAG_DISABLE_LERP     = 0b0000000000010000;
$FLAG_DISABLE_LIMITS   = 0b0000000000100000;
$FLAG_DISABLE_MOUSE    = 0b0000000001000000;

//Function(s)
function is_mode( int $test_mode ) : bool
{
	//!TODO
	//Returns true if the mode of the script matches the mode provided via parameter.
	//Used to enable/include vs disable/exclude parts of the script during generation.
	return true;
}
//Method(s)
function print_header(string $s, array $defaults = null)
{
	//Prints header comment block used to mark areas in the generated script.
	//Returns void(method).
	global $DEFAULT_OPTIONS;
	if($defaults == null)
	{
		//No defaults provided, using the generic defaults.
		$defaults = $DEFAULT_OPTIONS["global_defaults"];
	}
	elseif(isset($defaults["global_defaults"]))
	{
		//Was probably passed all defaults, this function is only using globals.
		$defaults = $defaults["global_defaults"];
	}
	$s = trim($s);
	$s_len = strlen($s);
	//Future proofing in-case comment marker changes to more than one character in the future e.g. //
	$c_len = strlen($defaults["comment_char"]);
	$m_len = $s_len + (2 * $c_len) + 2;//Comment markers and padding are added to the begining and end
	$c_cnt = ceil($m_len / $c_len);
	$c_line = substr(str_repeat($defaults["comment_char"], $c_cnt), 0, $m_len);
	$m = ($defaults["comment_char"] . " " . $s . " " . $defaults["comment_char"]);
	print implode($defaults["end_line"], [$c_line, $m, $c_line, ""]);
}
function print_comment(string $p_comment, array $defaults = null, int $padding_amount = 1)
{
	//Prints an in-line comment using values from defaults with optional padding.
	global $DEFAULT_OPTIONS;
	if($defaults == null)
	{
		//No defaults provided, using the generic defaults.
		$defaults = $DEFAULT_OPTIONS["global_defaults"];
	}
	elseif(isset($defaults["global_defaults"]))
	{
		//Was probably passed all defaults, this function is only using globals.
		$defaults = $defaults["global_defaults"];
	}
	$lines = explode("" . $defaults["end_line"], $p_comment);
	$lines_count = count($lines);
	if((!$defaults["prefer_multiline"]) || ($lines_count <= 1))
	{
		//Print single-line comment(s)
		foreach($lines as $line)
			print("" . $defaults["comment_char"] . str_repeat(" ", $padding_amount) . $line . $defaults["end_line"]);
	} else {
		//Print a multiline comment
		print($defaults["comments_char"] . $defaults["end_line"]);
		print($p_comment);
		print($defaults["comments_char"] . $defaults["end_line"]);
	}
}
function print_comments(string|array $p_comments, array $defaults = null, int $padding_amount = 1)
{
	//Prints multiple comments using values from defaults with optional padding.
	global $DEFAULT_OPTIONS;
	if($defaults == null)
	{
		//No defaults provided, using the generic defaults.
		$defaults = $DEFAULT_OPTIONS["global_defaults"];
	}
	elseif(isset($defaults["global_defaults"]))
	{
		//Was probably passed all defaults, this function is only using globals.
		$defaults = $defaults["global_defaults"];
	}
	if(is_array($p_comments))
	{
		//Handle array(s) recursively
		print_comments(implode_r("" . $defaults["end_line"], $p_comments), $defaults, $padding_amount);
	} else {
		//Handle string(s)
		print_comment($p_comments, $defaults, $padding_amount);
	}
}
function print_tooltip_comments(array $a)
{
	//Prints tooltip for exported variables. Prints normal comments for non-exported variables and constants.
	if(empty($a))
	{
		//Used for manual formatting.
		return;
	}
	if(!isset($a["tooltip"]))
	{
		//Nothing to print.
		return;
	}
	$prefix = "";
	if(isset($a["export"]))
	{
		$prefix = $a["export"] ? "## " : "# ";
	}
	else
	{
		//Is a normal comment.
		$prefix = "# ";
	}
	foreach($a["tooltip"] as $i => $next)
	{
		print($prefix . $next . "\n");
	}
}
function print_constant(array $c)
{
	//Format:
	//{"tooltip":[],"name":"example_constant","type":"float","value":"PI","modes":0}
	if(empty($c))
	{
		//Empty/Null Arrays can be used for manual spacing.
		print("\n");
		return;
	}
	if($c["name"] == null or $c["value"] == null)
	{
		printd("Failed to print constant value. Array had undefined name or value.", "[WARN]: ");
		return;
	}
	print_tooltip_comments($c);
	print("const " . strtoupper($c["name"]));
	if($c["type"])
	{
		print(":" . $c["type"]);
	}
	print(" = " . $c["value"]);
	if(isset($c["comment"]))
	{
		print_comment($c["comment"]);
	}
	else
	{
		print("\n");
	}
}
function print_variable(array $v)
{
	global $default_prefix;
	//Format:
	//{"tooltip":[],"export":true,"name":"example_variable","type":"float","value":"PI","modes":0,"generate_default":true,"setget":"function_name"}
	//Validate inputs
	if($v == null)
	{
		//Used for manual formatting.
		print("\n");
		return;
	}
	if($v["export"] == null){ $v["export"] = false; }
	if($v["name"] == null or strlen($v["name"]) <= 0)//Warning: strlen(null) is deprecated.
	{
		printd("Failed to print variable. Variable name was undefined or empty.", "[WARN]: ");
		return;
	}
	if(!isset($v["generate_default"])){ $v["generate_default"] = false; }
	//Print tooltip(if needed.)
	print_tooltip_comments($v);
	//Print it.
	if($v["export"])
	{
		print("export");
		if($v["type"])
		{
			print("(" . $v["type"] . ")");
		}
		print(" ");
	}
	print("var " . $v["name"]);
	if($v["type"])
	{
		print(":" . $v["type"]);
	}
	if($v["value"])
	{
		print(" = ");
		if($v["generate_default"] == false)
		{
			print($v["value"]);
		}else{
			print("DEFAULT_" . strtoupper($v["name"]));
		}
	}
	if(isset($v["setget"])){ print(" setget " . $v["setget"]); }
	if(isset($v["comment"]))
	{
		print_comment($v["comment"]);
	}
	else
	{
		print("\n");
	}
}
function preprocess_variable_constants(array $variables)
{
	global $default_prefix, $json_data;
	//Adds any auto-defined default constant values from variables array to constants array.
	//Assumes json_data is loaded and valid.
	foreach($variables as $i => $next)
	{
		if(!isset($next["name"]))
		{
			array_push($json_data["constants"], array());//For manual formatting.
			continue;
		}
		if(isset($next["generate_default"]))
		{
			if(!$next["generate_default"]) continue;
			array_push($json_data["constants"], array(
			"name" => $default_prefix . $next["name"],
			"type" => $next["type"],
			"value" => $next["value"]
			));
		}
	}
}
function print_function(array $f)
{
	//Prints a GDScript function from json associative array
	$temp_flag = false;
	$temp_count = 0;
	if($f == null)
	{
		//Used for manual formatting.
		print("\n");
		return;
	}
	if($f["name"] == null or strlen($f["name"]) <= 0)//Warning: strlen(null) is deprecated.
	{
		printd("Failed to print function. Function name was undefined or empty.", "[WARN]: ");
		return;
	}
	print("func " . strtolower($f["name"]) . "(");
	if(isset($f["parameters"]))
	{
		$temp_count = count($f["parameters"]);
		if($temp_count > 0)
			$temp_flag = true;
	}
	if($temp_flag)
	{
		foreach($f["parameters"] as $i => $next)
		{
			print($next);
			if(($i + 1) < $temp_count)
				print(", ");
		}
	}
	$temp_flag = false;
	$temp_count = 0;
	print(")");
	if(isset($f["type"]))
	{
		print(" -> " . $f["type"]);
	}
	print(":\n");
	if(isset($f["code"]))
	{
		if(count($f["code"]) > 0)
			$temp_flag = true;
	}
	if($temp_flag)
	{
		foreach($f["code"] as $i => $next)
			print("\t" . $next . "\n");
	} else {
		print("\tpass\n");
	}
	$temp_flag = false;
}
function print_script( $json_data, $script_mode = 0 )
{
	//Processes and prints a GDScript from json_data
	//Pre-Processing
	preprocess_variable_constants($json_data["variables"]);
	//Print Header Comments
	if(isset($json_data["header_comments"]))
	{
		foreach($json_data["header_comments"] as $i => $next)
		{
			print_comment($next);
		}
		print("\n");
	}
	//Print Constants
	if(isset($json_data["constants"]))
	{
		print("########################\n");
		print("# Constants / Defaults #\n");
		print("########################\n");
		foreach($json_data["constants"] as $i => $next)
		{
			print_constant($next);
		}
		print("\n");
	}
	//Print Variables
	if(isset($json_data["variables"]))
	{
		print("##################################\n");
		print("# Variables / Exported Variables #\n");
		print("##################################\n");
		foreach($json_data["variables"] as $i => $next)
		{
			print_variable($next);
		}
		print("\n");
	}
	//Functions/Methods
	if(isset($json_data["functions"]))
	{
		print("####################################################\n");
		print("# Functions / Methods / Events / Signals / SetGets #\n");
		print("####################################################\n");
		foreach($json_data["functions"] as $i => $next)
		{
			print_function($next);
		}
		print("\n");
	}
}

// Start main execution.
printd("Script's main execution has started.");//!Debugging
$json_data = load_json('./Script.json');
if($json_data == null)
{
	printd("Failed to parse JSON data.");//!Debugging
	die();
}
// Display data(Debugging)
if($debug_mode)
{
	printd("Parsed JSON recursive value: ");
	print_r($json_data);
}
// Print
print_script($json_data);
//Finish
printd("Script has stopped.");//!Debugging
?>