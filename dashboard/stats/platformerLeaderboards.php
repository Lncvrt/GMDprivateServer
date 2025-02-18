<?php
session_start();
require "../incl/dashboardLib.php";
require "../".$dbPath."incl/lib/connection.php";
$dl = new dashboardLib();
require_once "../".$dbPath."incl/lib/mainLib.php";
require "../".$dbPath."incl/lib/exploitPatch.php";
$gs = new mainLib();
require "../".$dbPath."incl/lib/connection.php";
require "../".$dbPath."config/misc.php";
$dl->title($dl->getLocalizedString("levelLeaderboards"));
$dl->printFooter('../');
if(isset($_GET["page"]) AND is_numeric($_GET["page"]) AND $_GET["page"] > 0) {
	$page = ($_GET["page"] - 1) * 10;
	$actualpage = $_GET["page"];
} else {
	$page = 0;
	$actualpage = 1;
}
if(!isset($_GET["search"])) $_GET["search"] = "";
if(!isset($_GET["levelID"])) $_GET["levelID"] = "";
if(!isset($_GET["type"])) $_GET["type"] = "";
if(!isset($_GET["ng"])) $_GET["ng"] = "";
$srcbtn = $levels = "";
$levelID = ExploitPatch::number($_GET['levelID']);
$type = ExploitPatch::number($_GET['type']) ?: ($_SESSION['accountID'] != 0 ? 0 : 1);
$mode = ExploitPatch::charclean($_GET['mode']) ?: 'time';
$order = $mode == 'time' ? 'ASC' : 'DESC';
$modcheck = $gs->checkPermission($_SESSION["accountID"], "dashboardModTools");
$leaderboardDeleteCheck = $gs->checkPermission($_SESSION["accountID"], "dashboardDeleteLeaderboards");
$query = $db->prepare("SELECT * FROM levels WHERE levelID = :levelID".(!$modcheck ? ' AND unlisted = 0' : ''));
$query->execute([':levelID' => $levelID]);
$level = $query->fetch();
if(empty($level) || $level['levelLength'] != 5) die($dl->printSong('<div class="form">
	<h1>'.$dl->getLocalizedString("errorGeneric").'</h1>
	<form class="form__inner" method="post" action="">
		<p id="dashboard-error-text">'.$dl->getLocalizedString("emptyPage").'</p>
		<button type="button" onclick="a(\'\', true, false, \'GET\')" class="btn-primary">'.$dl->getLocalizedString("dashboard").'</button>
	</form>
</div>', 'browse'));
if($_SESSION['accountID'] != 0) {
	if(isset($_POST['deleteLeaderboardID'])) {
		$deleteLeaderboardID = ExploitPatch::number($_POST['deleteLeaderboardID']);
		$query = $db->prepare("SELECT count(*) FROM platscores WHERE ID = :deleteLeaderboardID");
		$query->execute([':deleteLeaderboardID' => $deleteLeaderboardID]);
		$leaderboard = $query->fetch();
		if($leaderboard && $leaderboardDeleteCheck) {
			$query = $db->prepare("DELETE FROM platscores WHERE ID = :deleteLeaderboardID");
			$query->execute([':deleteLeaderboardID' => $deleteLeaderboardID]);
		}
	}
}
switch($type) {
	case 1:
		$query2 = $db->prepare("SELECT * FROM platscores WHERE levelID = :levelID AND time > 0 ORDER BY {$mode} {$order} LIMIT 10 OFFSET $page");
		$countQuery = "SELECT count(*) FROM platscores WHERE levelID = :levelID AND time > 0";
		$query2args = [':levelID' => $levelID];
		break;
	case 2:
		$query2 = $db->prepare("SELECT * FROM platscores WHERE levelID = :levelID AND timestamp > :time AND time > 0 ORDER BY {$mode} {$order} LIMIT 10 OFFSET $page");
		$countQuery = "SELECT count(*) FROM platscores WHERE levelID = :levelID AND timestamp > :time AND time > 0";
		$query2args = [':levelID' => $levelID, ':time' => time() - 604800];
		break;
	default:
		$friends = $gs->getFriends($_SESSION['accountID']);
		$friends[] = $_SESSION['accountID'];
		$friends = implode(",", $friends);
		$query2 = $db->prepare("SELECT * FROM platscores WHERE levelID = :levelID AND accountID IN ($friends) AND time > 0 ORDER BY {$mode} {$order} LIMIT 10 OFFSET $page");
		$countQuery = "SELECT count(*) FROM platscores WHERE levelID = :levelID AND accountID IN ($friends) AND time > 0";
		$query2args = [':levelID' => $levelID];
		break;
}
$query2->execute($query2args);
$result = $query2->fetchAll();
if(isset($_SERVER["HTTP_REFERER"])) $back = '<form style="display: contents;" method="post" action="'.$_SERVER["HTTP_REFERER"].'">
	<button type="button" onclick="a(\''.$_SERVER["HTTP_REFERER"].'\', true, true, \'GET\')" class="goback no-margin">
		<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
	</button>
</form>';
else $back = '';
$typeMenu = '<button type="button" class="goback no-margin" id="navbarDropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
	<i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
</button>
<div onclick="event.stopPropagation()" class="dropdown-menu dropdown-menu-left" aria-labelledby="navbarDropdownMenuLink">
	'.($_SESSION['accountID'] != 0 ? '<button type="button" class="dropdown-item" onclick="a(\'stats/platformerLeaderboards.php?levelID='.$levelID.'\', true, true, \'GET\')">
		<div class="icon"><i class="fa-solid fa-user-group"></i></div>
		'.$dl->getLocalizedString("friends").'
	</button>' : '').'
	<button type="button" class="dropdown-item" onclick="a(\'stats/platformerLeaderboards.php?levelID='.$levelID.'&type=1\', true, true, \'GET\')">
		<div class="icon"><i class="fa-solid fa-globe"></i></div>
		'.$dl->getLocalizedString("all").'
	</button>
	<button type="button" class="dropdown-item" onclick="a(\'stats/platformerLeaderboards.php?levelID='.$levelID.'&type=2\', true, true, \'GET\')">
		<i class="fa-solid fa-clock" style="position: absolute;font-size: 10px;margin: 0px 5px 5px -7px;" aria-hidden="false"></i>
		<div class="icon"><i class="fa-solid fa-globe"></i></div>
		'.$dl->getLocalizedString("weekLeaderboards").'
	</button>
</div>';
$x = 0;
foreach($result as &$leaderboard) {
	$x++;
	$query = $db->prepare("SELECT * FROM users WHERE extID = :extID");
	$query->execute([':extID' => $leaderboard['accountID']]);
	$action = $query->fetch();
	$query = $db->prepare("SELECT levelLength FROM levels WHERE levelID = :levelID");
	$query->execute([':levelID' => $levelID]);
	$action['levelLength'] = $query->fetchColumn();
	$time = '<p class="profilepic">'.($leaderboard["time"] / 1000).' <i class="fa-solid fa-clock"></i></p>';
	$points = '<p class="profilepic">'.$leaderboard["points"].' <i class="fa-solid fa-circle-play"></i></p>';
	$leaderboard['uploadDate'] = $leaderboard['timestamp'];
	$leaderboards .= $dl->generateLeaderboardsCard($x, $leaderboard, $action, $leaderboardDeleteCheck, $time.$points);
}
if(empty($leaderboards)) $leaderboards = '<div class="empty-section">
	<i class="fa-solid fa-chart-simple"></i>
	<p>'.$dl->getLocalizedString('noLeaderboards').'</p>
</div>';
$pagel = '<div class="form new-form">
	<h1 class="goback-title">'.$back.$dl->getLocalizedString("levelLeaderboards").$typeMenu.'</h1>
	<div class="form-control new-form-control level-card">
		'.$dl->generateLevelsCard($level, $modcheck).'
	</div>
	<div class="form-control new-form-control">
		'.$leaderboards.'
	</div>
</div>';
/*
	bottom row
*/
//getting count
$query = $db->prepare($countQuery);
$query->execute($query2args);
$packcount = $query->fetchColumn();
$pagecount = ceil($packcount / 10);
$bottomrow = $dl->generateBottomRow($pagecount, $actualpage);
$dl->printPage($pagel.$bottomrow, true, "browse");
?>