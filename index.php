<!DOCTYPE>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>Tour of Crete | Live Classifications</title>
	<link href="csc.css?v=5" rel="stylesheet" type="text/css">
	<link rel="shortcut icon" href="toc_logo.ico" />
	<link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
	<style>
	</style>
<?php
	#header('refresh: 5;'); 
?>
<header>
	
</header>
<body>

<?php
$servername = "localhost";
$username = "csc";
$password = "@n@t0l1k1kr1t1";
$dbname = "tourofcrete";

$GLOBALS['debug_on'] = true;


function DEBUG_MSG($msg) {
	if ($GLOBALS['debug_on']) {
		echo '<pre class="for_debug">'; 
		echo($msg . "<br>");
		echo '</pre>';
	}
}

function DEBUG_ARRAY($r) {
	if ($GLOBALS['debug_on']) {
		echo '<pre class="for_debug">'; 
		print_r($r);
		echo '</pre>';
	}
}

function IsNullOrEmptyString($str){
    return (!isset($str) || trim($str) === '');
}

function IsNullDecimal($dec){
    return (!isset($dec));
}

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
//
// Αρχικοποίηση πίνακα κατηγοριών		
$categories = array(array("code"=>"C1", "descr"=>"Category 18-35", "exists" => false),
					array("code"=>"C2", "descr"=>"Category 36-55", "exists" => false),
					array("code"=>"C3", "descr"=>"Category 56+", "exists" => false),
					array("code"=>"F", "descr"=>"Category Female", "exists" => false),
				 );

//DEBUG_MSG("Κατηγορίες ποδηλατών:");				 
//DEBUG_ARRAY($categories); 
/*
****************************************************************************************************************************************/

//
// Εντοπίζουμε το race_id
$sql = "select race_id
		from toc_races
		where toc_races.race_date = '" . date("Y") ."'" ;
$result = $conn->query($sql);
if ($result->num_rows > 0) {
	$row = $result->fetch_assoc();
	$raceID = $row["race_id"];
} else {
	$conn->close();
	die("FATAL ERROR: No race this year!!!" . "<br>");
}
//DEBUG_MSG("Κωδικός αγώνα:");				 
//DEBUG_MSG($raceID); 
/*
****************************************************************************************************************************************/


//
// Θα αρχικοποιήσουμε τον πίνακα 'toc_timetable' αν χρειάζεται
//
// 1. Βρίσκουμε τους ποδηλάτες που δεν έχουν εγγραφές στον πίνακα 'toc_timetable' ...
//    Το κριτήριο για τις διάφορες περιπτώσεις είναι το εξής:
//    $where_in_clause == "@"              ->   όλοι οι ποδηλάτες έχουν καταχωρήσεις στον πίνακα 'toc_timetable'
//    $where_in_clause == "(x1,x2, ...)"   ->   συγκεκριμένοι ποδηλάτες δεν έχουν καταχωρήσεις στον πίνακα 'toc_timetable' 
//                                              (καλύπτει και την περίπτωση που κανένας ποδηλάτης δεν έχει καταχωρήσεις στον πίνακα 'toc_timetable')
$sql = " SELECT * 
		 FROM toc_cyclist as c  LEFT JOIN (SELECT * FROM toc_timetable WHERE toc_timetable.race_id = " . $raceID . ") as t  ON c.deviceid = t.device_id
		 WHERE c.race_id =  " . $raceID;
$result = $conn->query($sql);
if ($result->num_rows > 0) {
	$where_in_clause = "(";
	$at_least_one = 0;
	while($row = $result->fetch_assoc()) {
		if (IsNullDecimal($row["timetable_id"])) {
			$at_least_one = 1;
			$where_in_clause = $where_in_clause . $row["deviceid"] . ",";
		}
	}
	if ($at_least_one == 0) {
		$where_in_clause = "@";
	} else {
		$pos = strrpos($where_in_clause, ","); // replace last , with )
		$where_in_clause    = substr_replace( $where_in_clause , ")" , $pos , 1 );
	}
} else {
	die("Δεν έχουν δηλωθεί ποδηλάτες για τον αγώνα!!!");
}
//
// 2. Εισάγουμε για αυτούς τους ποδηλάτες εγγραφές για όλες τις ανηφόρες του αγώνα 
//$sql = "SELECT * FROM tourofcrete.toc_timetable where race_id =" . $raceID;
//$result = $conn->query($sql);

if ($where_in_clause != "@") {
	$sql2 = "select toc_cyclist.race_id as raceID,  toc_cyclist.deviceid as deviceID, toc_stages.stage_id as stageID, toc_ascents.ascent_id as ascentID 
	        from toc_cyclist inner join toc_stages on toc_cyclist.race_id = toc_stages.race_id inner join toc_ascents on toc_stages.stage_id = toc_ascents.stage_id
	        where toc_stages.race_id =" . $raceID . " AND toc_cyclist.deviceid IN " . $where_in_clause ;
	$result2 = $conn->query($sql2);
	if ($result2->num_rows > 0) {
		$nof_timetable = 1;
		while($row2 = $result2->fetch_assoc()) {
			$sql_insert = "insert into toc_timetable (race_id, stage_id, ascent_id, device_id, ascent_time, locked)
							values(" . $row2[raceID]. "," . $row2[stageID]. "," . $row2[ascentID]. "," . $row2[deviceID]. ",'00:00:00', 0)";
			if ($conn->query($sql_insert) == FALSE) {
				echo "Error: " . $sql_insert . "<br>" . $conn->error;
			}
			$nof_timetable = $nof_timetable + 1;
		}
	}	
}
/*
****************************************************************************************************************************************/


//
// Εντοπίζουμε τα stages του αγώνα
$sql = "select toc_stages.*
		from toc_stages
		where toc_stages.race_id = " . $raceID .
		" order by toc_stages.stage_rank asc";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
	$nof_stages = 1;
	while($row = $result->fetch_assoc()) {
		$stage["Name"] = $row["stage_name"];
		$stage["ID"] = $row["stage_id"];
		$stages[$nof_stages] = $stage;
		$nof_stages = $nof_stages + 1;
	}
} else {
	$conn->close();
	die("FATAL ERROR: Race with no stages!!! This should never happen!!!" . "<br>");
}
$nof_stages = $nof_stages - 1;

//DEBUG_MSG("Ο Αγώνας περιλαμβάνει τα εξής " .$nof_stages . " stages:");
//DEBUG_ARRAY($stages); 
/*
****************************************************************************************************************************************/


//
// Εντοπίζουμε σε ποιό stage βρίσκεται ο αγώνας
// $nof_stages : Πόσα στάδια έχει ο αγώνας
// $current_stage : Σε ποιό στάδιο βρίσεκται ο αγώνας
// $selected_stage: Ποιό στάδιο έχουμε επιλέξει να δούμε από τα links
/*
$sql = "select toc_timetable.*, toc_stages.stage_rank
		from toc_timetable inner join toc_stages on toc_stages.stage_id = toc_timetable.stage_id
		where toc_timetable.race_id = " . $raceID . " and locked = 1
		order by toc_stages.stage_rank desc
		limit 1";
*/
$sql = "SELECT s.stage_id, s.stage_rank
		FROM tc_events e inner join toc_ascents a on e.geofenceid = a.geofence_id inner join toc_stages s on a.stage_id = s.stage_id
		WHERE e.type ='geofenceExit' and s.race_id = " . $raceID . "
		order by s.stage_rank desc
		limit 1";

$result = $conn->query($sql);
if ($result->num_rows > 0) {
	$row = $result->fetch_assoc();
	$current_stage = $row["stage_rank"];
	$current_stage_id = $row["stage_id"];
	$current_stage_id = $stages[$current_stage]["ID"];
	
} else {
	$current_stage = 1;
	$current_stage_id = $stages[$current_stage]["ID"];
}
/*
****************************************************************************************************************************************/

//
// Ελέγχουμε αν έχει επιλεχτεί κάποιο στάδιο από τα link της ιστοσελίδας για να θέσουμε
// την κατάλληλη τιμή στο $selected_stage
// Από εδώ και κάτω, δουλεύουμε με το $selected_stage KAI OXI TO $current_stage
$pageWasRefreshed = isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] === 'max-age=0';
if (isset($_GET['stage']) && !($pageWasRefreshed)) {
	$selected_stage = $_GET['stage'];
	$selected_stage_id = $stages[$selected_stage]["ID"];
} else {
	$selected_stage = $current_stage;
	$selected_stage_id = $current_stage_id;
}
/*
****************************************************************************************************************************************/

//
// Εντοπίζουμε τις ανηφόρες του $current_stage
$sql = "select toc_ascents.*
		from toc_ascents
		where stage_id = " . $selected_stage_id .
		" order by ascent_rank asc";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
	$nof_ascents = 1;
	while($row = $result->fetch_assoc()) {
		$ascent["Name"] = $row["ascent_name"];
		$ascent["ID"] = $row["ascent_id"];
		$ascent["GeofenceID"] = $row["geofence_id"];
		$ascents[$nof_ascents] = $ascent;
		$nof_ascents = $nof_ascents + 1;
	}
} else {
	$conn->close();
	die("FATAL ERROR: Stage with no ascents!!! This should never happen!!!" . "<br>");
}

//DEBUG_MSG("Βρισκόμαστε στο stage " . $selected_stage . " που έχει stage_id ". $selected_stage_id . " και " . count($ascents). " ανηφόρες:");
//DEBUG_ARRAY($ascents); 
/*
****************************************************************************************************************************************/

//
// Στο σημείο αυτό, θα ενημερώσουμε τις εγγραφές στον πίνακα 'toc_timetable' για όλους τους ποδηλάτες με τους χρόνους κάθε ανηφόρας 
// από τον πίνακα 'tc_events'
// ΠΡΟΣΟΧΗ:
// - Η διαδικασία αυτή θα γίνει μόνο για stages που βρίσκονται σε εξέλιξη και όχι για αυτά που έχουν τελειώσει
// - Η ενημέρωση θα γίνεται μόνο όταν 'toc_timetable.locked = 0'

if ($selected_stage == $current_stage) {
	//
	// Εντοπίζουμε όλες τις ανηφόρες των ποδηλατών που δεν έχουν "περαστεί" στον πίνακα 'toc_timetable' ...
	$events_sql = "SELECT e.type, e.servertime, e.deviceid, a.ascent_id, t.stage_id, t.race_id, t.ascent_time
			FROM tc_events e inner join toc_ascents a on e.geofenceid = a.geofence_id inner join toc_timetable t on e.deviceid = t.device_id
			WHERE (type='geofenceEnter' or type ='geofenceExit') and 
				   t.locked = 0 and 
				   a.stage_id = " . $selected_stage_id . " and
				   a.ascent_id = t.ascent_id 
			order by servertime";
	$events_result = $conn->query($events_sql);
	if ($events_result->num_rows > 0) {
		$first_geofence_found = false;
		while($row = $events_result->fetch_assoc()) {
			$key = $row["deviceid"] . "#" . $row["ascent_id"] . "#" . $row["stage_id"] . "#" . $row["race_id"];
			if ($first_geofence_found) {
				// Σε περίπτωση που περνάμε από το ίδιο Geofence δύο φορές, μετράμε μόνο το πρώτο πέρασμα
				break;
			} 
			if ($row["type"] == "geofenceEnter") {
				$timetable_rec["deviceid"] = $row["deviceid"];
				$timetable_rec["ascent_id"] = $row["ascent_id"];
				$timetable_rec["stage_id"] = $row["stage_id"];
				$timetable_rec["race_id"] = $row["race_id"];
				$timetable_rec["time_in"] = $row["servertime"];
				$timetable[$key] = $timetable_rec;
			} elseif ($row["type"] == "geofenceExit") {
				if (array_key_exists($key, $timetable)) {
					$timetable[$key]["time_out"] = $row["servertime"];
					$time = strtotime($timetable[$key]["time_out"]) - strtotime($timetable[$key]["time_in"]);
					$timetable[$key]["ascent_time"] = gmdate('H:i:s', $time);
					$timetable[$key]["update"] = "ok";
					$first_geofence_found = true;
				}
			}
			
		}
	}
	//
	// .. και τις ενημερώνουμε τον πίνακα 'toc_timetable'
	foreach ($timetable as $key => $timetable_rec) {
		if ($timetable[$key]["update"] == "ok") {
			$sql_update = "UPDATE toc_timetable set ascent_time = '" . $timetable[$key]["ascent_time"] . "', locked=1 
					WHERE race_id = ". $timetable_rec["race_id"] . 
					" and stage_id = " . $timetable_rec["stage_id"] . 
					" and ascent_id = " . $timetable_rec["ascent_id"] . 
					" and device_id = " . $timetable_rec["deviceid"] . 
					" and locked = 0";
			//DEBUG_MSG("--->". $sql_update);
			if ($conn->query($sql_update) == FALSE) {
				echo "Error: " . $sql_update . "<br>" . $conn->error;
			}
		}
	}
}
//DEBUG_ARRAY($timetable);
//die();

/*
****************************************************************************************************************************************/

//
// Εντοπίζουμε τα στοιχεία των ποδηλατών και τους συνολικούς τους χρόνους (χρόνος στα stages που έχουν ολοκληρωθεί)
if ($selected_stage == 1) {
	$where_in_clause = "(" . $stages[1]["ID"] . ")";
	$cyclists_sql = "select toc_cyclist.name, toc_cyclist.bib, toc_cyclist.nationality, toc_cyclist.category, toc_cyclist.DNF, toc_cyclist.deviceid,SEC_TO_TIME( SUM( TIME_TO_SEC( ascent_time ) ) )  as race_time
						from toc_cyclist inner join toc_timetable on toc_cyclist.deviceid = toc_timetable.device_id
						where toc_timetable.stage_id in  " . $where_in_clause  .
						" group by device_id
						order by device_id" ;
	$cyclists_result = $conn->query($cyclists_sql);		
} else {
	$where_in_clause = "(";
	for ($x = 1; $x < $selected_stage; $x++) {
		$where_in_clause = $where_in_clause . $stages[$x]["ID"] . ",";
	}
	$pos = strrpos($where_in_clause, ","); // replace last , with )
	$where_in_clause    = substr_replace( $where_in_clause , ")" , $pos , 1 );
	
	$cyclists_sql = "select toc_cyclist.name, toc_cyclist.bib, toc_cyclist.nationality, toc_cyclist.category, toc_cyclist.DNF, toc_cyclist.deviceid,SEC_TO_TIME( SUM( TIME_TO_SEC( ascent_time ) ) )  as race_time
						from toc_cyclist inner join toc_timetable on toc_cyclist.deviceid = toc_timetable.device_id
						where toc_timetable.stage_id in  " . $where_in_clause  .
						" group by device_id
						order by device_id" ;
	$cyclists_result = $conn->query($cyclists_sql);
}

//DEBUG_MSG($cyclists_sql);
//DEBUG_MSG("Συμμετέχοντες " .$cyclists_result->num_rows);
//die();
/*
****************************************************************************************************************************************/

//
// Θα υπολογίσουμε τώρα για κάθε ποδηλάτη το χρόνο που έχει σε κάθε ανηφόρα στο $selected_stage και θα ενημερώσουμε τη δομή
// $cyclists
//
// Array
// (
//    [1] => Array
//        (
//            [Name] => Kimmy TARANTO
//            [bib] => 49
//            [DeviceID] => 7
//            [Country] => au
//            [Category] => F
//            [DNF] => 0
//            [Total] => 00:55:07
//            [Ascents] => Array
//                (
//                    [1] => Array
//                        (
//                            [Name] => ascent1
//                            [Time] => 00:55:07
//                        )
//
//                    [2] => Array
//                        (
//                            [Name] => ascent2
//                            [Time] => 00:00:00
//                        )
//
//                    [3] => Array
//                        (
//                            [Name] => ascent3
//                            [Time] => 00:00:00
//                        )
//
//                )
//
//            [StageTotal] => 00:55:07
//        )
//
//    [2] =>
//     
//    ...
//

// Βρίσκει για όλους τους ποδηλάτες το χρόνο σε κάθε ανηφόρα του $selected_stage ...
$ascents_sql = "SELECT toc_timetable.device_id, toc_ascents.ascent_id, toc_ascents.ascent_name, toc_timetable.ascent_time
					FROM toc_ascents inner join toc_timetable  on toc_ascents.ascent_id = toc_timetable.ascent_id
                    where toc_timetable.stage_id = " . $selected_stage_id .
					" order by toc_timetable.device_id,  toc_ascents.ascent_rank";
//
// ... Βρίσκει τους συνολικούς μέχρι στιγμή χρόνους του $selected_stage (στις ανηφόρες που έχουν ολοκληρωθεί) ...
$stage_time_sql = "SELECT device_id, SEC_TO_TIME( SUM( TIME_TO_SEC( ascent_time ) ) ) as stage_time
					FROM toc_timetable
					where stage_id = " . $selected_stage_id .
					" group by device_id
					order by device_id";
$ascents_result = $conn->query($ascents_sql);
$stage_time_result = $conn->query($stage_time_sql);

if ($cyclists_result->num_rows > 0) {
	$nof_cyclists = 1;
	while($row = $cyclists_result->fetch_assoc()) {
		//
		// Βασικά στοιχεία ποδηλάτη (Όνομα | Κωδικός συσκευής | Νούμερο φανέλας |Χώρα | Κατηγορία | Συνολικός χρόνος στα προηγούμενα στάδια)
		$cyclist["Name"] = $row["name"];
		$cyclist["bib"] = $row["bib"];
		$cyclist["DeviceID"] = $row["deviceid"];
		$cyclist["Country"] = $row["nationality"];
		$cyclist["Category"] = $row["category"];
		switch ($cyclist["Category"]) {
		case "C1":
			$categories[0]["exists"] = true;
			break;
		case "C2":
			$categories[1]["exists"] = true;
			break;
		case "C3":
			$categories[2]["exists"] = true;
			break;
		case "F":
			$categories[3]["exists"] = true;
			break;
		}
		$cyclist["DNF"] = $row["DNF"];
		$cyclist["Total"] = $row["race_time"]; // δηλαδή, το συνολικό χρόνο στα stages που έχουν ολοκληρωθεί
		//
		// Στοιχεία (Όνομα | χρόνος) για τις ανηφόρες που έχει διανύσει ο ποδηλάτης στο τρέχων stage ($selected_stage)
		if ($ascents_result->num_rows > 0) {
			$nof_ascents = 1;
			while($ascent_row = $ascents_result->fetch_assoc()) {
				if ($ascent_row["device_id"] == $cyclist["DeviceID"]) {
					$current_ascent["Name"] = $ascent_row["ascent_name"];
					$current_ascent["Time"] = $ascent_row["ascent_time"];
					$current_ascents[$nof_ascents] = $current_ascent;
					$nof_ascents = $nof_ascents + 1;
				}
			}
		} 
		$cyclist["Ascents"] = $current_ascents;
		//
		// συνολικός χρόνος ποδηλάτη στο τρέχων stage
		if ($stage_time_result->num_rows > 0) {
			while($stage_time_row = $stage_time_result->fetch_assoc()) {
				if ($stage_time_row["device_id"] == $cyclist["DeviceID"]) {
					$cyclist["StageTotal"] = $stage_time_row["stage_time"];
					break;
				}
			}
		} 
		$cyclists[$nof_cyclists] = $cyclist;
		$nof_cyclists = $nof_cyclists + 1;
		mysqli_data_seek($ascents_result, 0) ;
		mysqli_data_seek($stage_time_result, 0) ;
		$current_ascents = null;
	}
} else {
	$nof_cyclists = 1;
}
$conn->close();
$nof_cyclists = $nof_cyclists - 1;

//DEBUG_ARRAY($cyclists);
//die();
/*
****************************************************************************************************************************************/

//
// Θα υπολογίσω στο σημείο αυτό τον χρόνο κατάταξης για το stage (sortTime)
// Σε αυτούς που υπολείπονται κάποιων ανήφορων θα βάζω χρόνο 18000 δευτερόλεπτα (5 ώρες) για 
// κάθε ανηφόρα που υπολείπονται. Στο χρόνο κατάταξης θα προσθέτω και το χρόνο αγώνα (χρόνος
// στα stages που έχουν ολοκληρωθεί)
// Επίσης, αν ένα ποδηλάτης έχει τερματίσει θα ενημερώσω το Total έτσι ώστε να περιλαμβάνει και το
// χρόνο του current stage

for ($x = 1; $x <= $nof_cyclists; $x++) {
	$penalty = 0;
	for ($z = 1; $z <= count($cyclists[$x]["Ascents"]); $z++) {
		if ($cyclists[$x]["Ascents"][$z]["Time"]=="00:00:00") {
			$penalty = $penalty + 18000;
		}
	}
	$str_time = $cyclists[$x]["StageTotal"];
	sscanf($str_time, "%d:%d:%d", $hours, $minutes, $seconds);
	$stage_time_seconds = isset($hours) ? $hours * 3600 + $minutes * 60 + $seconds : $minutes * 60 + $seconds;
	if ($selected_stage > 1) {
		$str_time = $cyclists[$x]["Total"];
		sscanf($str_time, "%d:%d:%d", $hours, $minutes, $seconds);
		$race_time_seconds = isset($hours) ? $hours * 3600 + $minutes * 60 + $seconds : $minutes * 60 + $seconds;
		$time_seconds = $stage_time_seconds + $race_time_seconds;
	} else {
		$time_seconds = $stage_time_seconds;
	}
	$cyclists[$x]["SortTime"] =  $time_seconds + $penalty;
	//echo "<b>" . $cyclists[$x]["bib"]. " " .$cyclists[$x]["SortTime"] . "</b><br>";
	
	if ($penalty == 0 && ($selected_stage > 1)) {  // if ο ποδηλάτης τερμάτισε σε ένα στάδιο εκτός από το 1ο
		$time = strtotime($cyclists[$x]["Total"]) + strtotime($cyclists[$x]["StageTotal"]) - strtotime('00:00:00');
		$cyclists[$x]["Total"] = date('H:i:s', $time);
	}
}
/*
****************************************************************************************************************************************/
//DEBUG_ARRAY($cyclists);
//die();
//
// Θα ταξινομήσω τώρα τη δομή μου πρώτα ...
//
// ... Ταξινόμηση με βάση το SortTime ...
for ($a = 1; $a <= $nof_cyclists; $a++) { 
	for ($b = 1; $b <= $nof_cyclists -1; $b++) { 
		//if (($cyclists[$b +1]["SortTime"] < $cyclists[$b]["SortTime"]) && ($cyclists[$b +1]["DNF"] == 0) ) { 
		if (($cyclists[$b +1]["SortTime"] < $cyclists[$b]["SortTime"])) { 
			$temp = $cyclists[$b]; 
			$cyclists[$b] = $cyclists[$b +1]; 
			$cyclists[$b +1] = $temp; 
		} 
	} 
} 
//
// ... και μετά Ταξινόμηση με βάση το DNF 
for ($a = 1; $a <= $nof_cyclists; $a++) { 
	for ($b = 1; $b <= $nof_cyclists -1; $b++) { 
		//if (($cyclists[$b +1]["SortTime"] < $cyclists[$b]["SortTime"]) && ($cyclists[$b +1]["DNF"] == 0) ) { 
		if (($cyclists[$b +1]["DNF"] < $cyclists[$b]["DNF"])) { 
			$temp = $cyclists[$b]; 
			$cyclists[$b] = $cyclists[$b +1]; 
			$cyclists[$b +1] = $temp; 
		} 
	} 
}
/*
****************************************************************************************************************************************/

?> 

<img id="logo" src="images/the-tour-of-crete--cyclosportive.jpg">
<br><br>
<!--
<p style="color:red">
Congratulations to everyone for the amazing 5rd day of Tour of Crete ...
</p>

<p style="color:green">
37 Terrence M SHIELS deserves a yellow jersey because he ended up to Ierapetra first of all!
</p>

</p>
<p style="color:green">
40 Philippe SUSSHOLZ had the unfortunate choice to had lunch within a timed area (4.5 Sfaka) and succeeded 1:31:31 in that region :-)<br>
I guess he enjoyed that more than everyone.
</p>
<p style="color:green">
51 Marc STRITTMATTER we missed you. Only Christoph is happy :-)
</p>
!-->
<div id="wrapper">
<div id="stage_links">
<?php
	$html_str = "";
	for ($a = 1; $a <= $current_stage; $a++) {
		if ($a == $selected_stage) {
			$html_str = $html_str . '<a class="stage_links"> STAGE ' . $a . '</a> | ';
		} else {
			$html_str = $html_str . '<a href = "http://live.tourofcrete.gr/index.php?stage=' . $a .'" class="stage_links"> STAGE ' . $a . '</a> | ';
		}
	}
	$pos = strrpos($html_str, "|"); // replace last | with ''
	$html_str = substr_replace( $html_str , "" , $pos , 1 );
	echo ($html_str);
?>
</div>
<div id="stage_caption">
<?php
echo "<h3>" . $stages[$selected_stage]["Name"] . "</h3>";
?>
</div>
	<?php
	for ($a = 0; $a <= 3; $a++) {
		if ($categories[$a]["exists"]) {
			echo '<table>';
			echo '<tr>';
			echo '<th id="rank_hdr">#</th>';
			echo '<th>Cyclist [' . $categories[$a]["descr"] . ']</th>';
				for ($x = 1; $x <= count($ascents); $x++) {
					echo '<th id="ascent_hdr">' . $ascents[$x]["Name"] . '</th>';
				}
			echo '<th>Stage Timing</th>';
			echo '<th>Total Timing</th>';
			echo '</tr>';
			$b = 1;
			for ($x = 1; $x <= $nof_cyclists; $x++) {
				if ($cyclists[$x]["Category"] == $categories[$a]["code"]) {
					if ($b % 2 == 0) {
						echo '<tr class="even_tr">';
					} else {
						echo '<tr class="odd_tr">';
					}
					echo '<td class="rank">' . $b  . '.</td>';
					echo '<td><img class="flag" src="images/flags/' . $cyclists[$x]["Country"] . '.png">';
					echo '<span class="bib">' . $cyclists[$x]["bib"] . '</span>';
					echo '<span class="athlet">' . $cyclists[$x]["Name"] . '</span>';
					if ($cyclists[$x]["DNF"] == 1) {
						echo '<span class="DNF">DNF</span>';
					}
					echo '</td>';
					for ($z = 1; $z <= count($ascents); $z++) {
						//
						// Η παρακάτω συνθήκη πιάνει τις περιπτώσεις όπου ένας ποδηλάτης δεν έχει τελειώσει όλες τις ανηφόρες ενός stage
						if ($cyclists[$x]["Ascents"][$z]["Time"] == "00:00:00") {
							echo '<td class="ascent">' . "  " . '</td>';
						} else {
							echo '<td class="ascent">' . $cyclists[$x]["Ascents"][$z]["Time"] . '</td>';
						} 
					}
					echo '<td class="time_elapsed_stage">' . $cyclists[$x]["StageTotal"] . '</td>';
					if ($cyclists[$x]["DNF"] == 1) {
						echo '<td class="time_elapsed_total">' . '' . '</td>';
					} else {
						echo '<td class="time_elapsed_total">' . $cyclists[$x]["Total"] . '</td>';
					}
					echo '</tr>';
					$b = $b + 1;
				}
			}
			echo '</table>';
			echo '<br><br>';
		}
	}	
	?>
</div>
</body>
</html>
