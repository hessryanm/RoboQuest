<?php
$debug = false;
$start = time();

class Creature{
	public $x = -1;
	public $y = -1;
	public $id = 0;
	// 0 = zombie, 1 = something else
	public $type = -1;
	public $state = "None";

	function __construct($eyed, $ex, $why, $t, $s){
		$this->x = $ex;
		$this->y = $why;
		$this->id = $eyed;
		$this->type = $t;
		$this->state = $s;
	}
}

class Point{
	public $x = -1;
	public $y = -1;

	function __construct($ex, $why){
		$this->x = $ex;
		$this->y = $why;
	}
}
$me = "RoroUiraArii";

$curl = curl_init("http://dev.i.tv:3026/players");
$data = '{"name":"RoroUiraArii","source":"https://github.com/FrizbeeFanatic14/RoroUiraArii"}';
$length = strlen($data);
$headers = array('Content-type: application/json', 'Content-legth: '.$length);
//die(print_r($headers));
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
$player_id = json_decode(curl_exec($curl));
//echo $player_id."\n";
curl_close($curl);

$game_info = curl_init("http://dev.i.tv:3026/game/info");
curl_setopt($game_info, CURLOPT_RETURNTRANSFER, true);
$game_info = json_decode(curl_exec($game_info));
 
$height = $game_info->height;
$width = $game_info->width;
$tick = $game_info->tick;

$make_zombie = curl_init("http://dev.i.tv:3026/players/".$player_id."/minions");
curl_setopt($make_zombie, CURLOPT_POST, true);
curl_setopt($make_zombie, CURLOPT_RETURNTRANSFER, true);

if($debug){
	$f = fopen("./zombies.log", "w");
	fclose($f);
} 

$killed = array();

while(1){
	$time_start = microtime_float();
	if ($debug) $file = fopen("./zombies.log", "a");
	//var_dump($file);
	if ($debug) fwrite($file, "---".$time_start."---\n");

	$zombies = array();
	$everything_else = array();

	$current = curl_init("http://dev.i.tv:3026/game/objects");
	curl_setopt($current, CURLOPT_RETURNTRANSFER, true);
	$current = json_decode(curl_exec($current));
	if ($debug) fwrite($file, "Zombies:\n");
	foreach($current as $bot){
		if ($bot->player == $me){
			if ($debug) fwrite($file, "\tID: ".$bot->id."\n");
			if ($debug) fwrite($file, "\tPosition: (".$bot->x.", ".$bot->y.")\n");
			if ($debug) fwrite($file, "\tState: ".$bot->state."\n\n");
			array_push($zombies, new Creature($bot->id, $bot->x, $bot->y, 0, $bot->state));
		} else{
			$key = array_search($bot->id, $killed);
			if ($key !== false && $bot->state == "Active") unset($killed[$key]);
			array_push($everything_else, new Creature($bot->id, $bot->x, $bot->y, 1, $bot->state));
		}
	}
	if ($debug) fwrite($file, "\n");

	if (count($zombies) == 0){
		if ($debug) fwrite($file, "There were no zombies\n");
		$x = rand(0, $width - 3);
		$y = rand(0, $height - 3);

		array_push($zombies, make_zombie($x, $y), $x, $y, 0);
		array_push($zombies, make_zombie($x+1, $y), $x+1, $y, 0);
		array_push($zombies, make_zombie($x, $y+1), $x, $y+1, 0);
	}
	foreach($everything_else as $thing){
		if($thing->state == "Dead"){
			if (in_array($thing->id, $killed)){
				make_zombie($thing->x, $thing->y);
			}
		}
	}
	foreach($killed as $key => $k){
		if (!exists($k)) unset($killed[$key]);
	}
	foreach($zombies as $zombie){
		$command = array();
		$next_to = creature_next_to_zombie($zombie);
		if($next_to != -1){
			$x = $zombie->x;
			$y = $zombie->y;
			if ($next_to == "Left") $x--;
			else if ($next_to == "Right") $x++;
			else if ($next_to == "Up") $y--;
			else $y++;
			$command['action'] = "Attack";
			$command['direction'] = $next_to;
			array_push($killed, get_creature_at($x, $y));
			if ($debug) fwrite($file, "Attacking ".get_creature_at($x, $y)."\n");
		} else{
			$point = get_closest($zombie);
			$dir_num = rand(0,1);
			
			if($zombie->x - $point->x < 0){
				$xdir = "Right";
			} else{
				$xdir = "Left";
			}
			if($zombie->y - $point->y < 0){
				$ydir = "Down";
			} else{
				$ydir = "Up";
			}

			if($dir_num) $dir = $xdir;
			else $dir = $ydir;

			// if(!valid_move($zombie, $dir)){
			// 	if ($dir == $xdir) $dir = $ydir;
			// 	else if ($dir == $ydir) $dir = $xdir;
			// }
			
			$command['action'] = "Move";
			$command['direction'] = $dir;
		}
		command_zombie($command, $zombie->id);
	}

	if ($debug) fwrite($file, "--------------------\n\n");
	if ($debug) fclose($file);

	$time_end = microtime_float();
	$time = $time_end - $time_start;
	$time *= 1000000;
	if ($time < ($tick * 1000)){
		usleep(($tick * 1000) - $time);
	}
}

function make_zombie($x, $y){
	global $make_zombie;
	global $file;
	if ($debug) fwrite($file, "Making zombie at (".$x.", ".$y."): ");

	$sprite = rand(4, 6);
	$data = array();
	$data["name"] = "zombie";
	$data["sprite"] = "undead-".$sprite."-3";
	$data["x"] = $x;
	$data["y"] = $y;
	$data = json_encode($data);
	$headers = array('Content-type: application/json', 'Content-legth: '.strlen($data));
	curl_setopt($make_zombie, CURLOPT_POSTFIELDS, $data);
	curl_setopt($make_zombie, CURLOPT_HTTPHEADER, $headers);
	$id = json_decode(curl_exec($make_zombie));
	if ($debug) fwrite($file, $id."\n");
}

function command_zombie($command, $id){
	global $player_id;
	global $file;

	if ($debug) fwrite($file, "Commanding ".$id.": \n");
	if ($debug) fwrite($file, "\tAction: ".$command['action']."\n");
	if ($debug) fwrite($file, "\tDirection: ".$command['direction']."\n");

	$ch = curl_init("http://dev.i.tv:3026/players/".$player_id."/minions/".$id."/commands");
	$command = json_encode($command);
	$headers = array('Content-type: application/json', 'Content-legth: '.strlen($command));
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $command);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_exec($ch);
	curl_close($ch);
}

function get_closest($zombie){
	global $everything_else;

	$distance = 999999;
	$close = new Point(-1, -1);
	foreach($everything_else as $thing){
		$xdist = $zombie->x - $thing->x;
		$ydist = $zombie->y - $thing->y;

		$x2 = $xdist * $xdist;
		$y2 = $ydist * $ydist;
		$dist = sqrt($x2 + $y2);

		if($dist < $distance){
			$distance = $dist;
			$close->x = $thing->x;
			$close->y = $thing->y;
		}
	}
	return $close;
}

function creature_in_location($x, $y){
	global $everything_else;
	global $zombies;

	foreach($everything_else as $thing){
		if ($thing->x == $x && $thing->y == $y){
			return $thing;
		}
	}
	foreach($zombies as $thing){
		if ($thing->x == $x && $thing->y == $y){
			return $thing;
		}
	}
	return null;
}

//-1 is none
function creature_next_to_zombie($zombie){
	$up = creature_in_location(($zombie->x), ($zombie->y)-1);
	$right = creature_in_location(($zombie->x)+1, ($zombie->y));
	$down = creature_in_location(($zombie->x), ($zombie->y)+1);
	$left = creature_in_location(($zombie->x)-1, $zombie->y);
	if($up != null && $up->type != 0 && $up->state == "Active") return "Up";
	if($right != null && $right->type != 0 && $right->state == "Active") return "Right";
	if($down != null && $down->type != 0 && $down->state == "Active") return "Down";
	if($left != null && $left->type != 0 && $left->state == "Active") return "Left";
	return -1;
}

function valid_move($zombie, $dir){
	global $everything_else;
	global $zombies;

	$x = $zombie->x;
	$y = $zombie->y;

	switch($dir){
		case "Up":
			$y -= 1;
			break;
		case "Right":
			$x += 1;
			break;
		case "Down":
			$y += 1;
			break;
		case "Left":
			$x -= 1;
			break;
	}

	if (creature_in_location($x, $y) != null){
		return false;
	}
	return true;
}

// function is_next_to_zombie($thing){
// 	global $zombies;

// 	foreach($zombies as $zombie){
// 		$isx = false;
// 		$isy = false;
// 		if ($thing->x + 1 == $zombie->x || $thing->x - 1 == $zombie->x){
// 			echo " X is true";
// 			$isx = true;
// 		}
// 		if ($thing->y + 1 == $zombie->y || $thing->y - 1 == $zombie->y){
// 			echo " Y is true";
// 			$isy = true;
// 		}
// 		if($isx == true && $isy == true){
// 			echo "Here";
// 			return true;
// 		}
// 	}
// 	return false;
// }

function microtime_float(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function get_creature_at($x, $y){
	global $zombies;
	global $everything_else;

	foreach ($zombies as $z){
		if ($z->x == $x && $z->y == $y) return $z->id;
	}
	foreach ($everything_else as $t){
		if ($t->x == $x && $t->y == $y) return $t->id;
	}
	return null;
}

function exists($id){
	global $zombies;
	global $everything_else;

	foreach($zombies as $z){
		if ($z->id == $id) return true;
	}
	foreach($everything_else as $z){
		if ($z->id == $id) return true;
	}
	return false;
}

?>