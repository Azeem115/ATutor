<?php
/************************************************************************/
/* ATutor																*/
/************************************************************************/
/* Copyright (c) 2002-2005 by Greg Gay, Joel Kronenberg & Heidi Hazelton*/
/* Adaptive Technology Resource Centre / University of Toronto			*/
/* http://atutor.ca														*/
/*																		*/
/* This program is free software. You can redistribute it and/or		*/
/* modify it under the terms of the GNU General Public License			*/
/* as published by the Free Software Foundation.						*/
/************************************************************************/
if (!defined('AT_INCLUDE_PATH')) { exit; }


function in_array_cin($strItem, $arItems)
{
   foreach ($arItems as $key => $strValue)
   {
       if (strtoupper($strItem) == strtoupper($strValue))
       {
		   return $key;
       }
   }
   return false;
} 


function get_tabs() {
	//these are the _AT(x) variable names and their include file
	/* tabs[tab_id] = array(tab_name, file_name,                accesskey) */
	$tabs[0] = array('content',       'edit.inc.php',          'n');
	$tabs[1] = array('properties',    'properties.inc.php',    'p');
	$tabs[2] = array('glossary_terms','glossary.inc.php',      'g');
	$tabs[3] = array('preview',       'preview.inc.php',       'r');
	$tabs[4] = array('accessibility', 'accessibility.inc.php', 'a');	

	return $tabs;
}

function output_tabs($current_tab, $changes) {
	global $_base_path;
	$tabs = get_tabs();
	echo '<table cellspacing="0" cellpadding="0" width="90%" border="0" summary="" align="center"><tr>';
	echo '<td>&nbsp;</td>';
	
	$num_tabs = count($tabs);
	for ($i=0; $i < $num_tabs; $i++) {
		if ($current_tab == $i) {
			echo '<td class="etabself" width="20%" nowrap="nowrap">';
			if ($changes[$i]) {
				echo '<img src="'.$_base_path.'images/changes_bullet.gif" alt="'._AT('usaved_changes_made').'" height="12" width="15" />';
			}
			echo _AT($tabs[$i][0]).'</td>';
		} else {
			echo '<td class="etab" width="20%">';
			if ($changes[$i]) {
				echo '<img src="'.$_base_path.'images/changes_bullet.gif" alt="'._AT('usaved_changes_made').'" height="12" width="15" />';
			}
			echo '<input type="submit" name="button_'.$i.'" value="'._AT($tabs[$i][0]).'" title="'._AT($tabs[$i][0]).' - alt '.$tabs[$i][2].'" class="buttontab" accesskey="'.$tabs[$i][2].'" onmouseover="this.style.cursor=\'hand\';" '.$clickEvent.' /></td>';
		}
		echo '<td>&nbsp;</td>';
	}	
	echo '</tr></table>';
}

// save all changes to the DB
function save_changes($redir) {
	global $contentManager, $db, $addslashes, $msg;

	$_POST['pid']	= intval($_POST['pid']);
	$_POST['cid']	= intval($_POST['cid']);

	$_POST['title'] = trim($_POST['title']);
	$_POST['body_text']	= trim($_POST['body_text']);
	$_POST['formatting'] = intval($_POST['formatting']);
	$_POST['keywords']	= trim($_POST['keywords']);
	$_POST['new_ordering']	= intval($_POST['new_ordering']);
	if ($_POST['setvisual']) { $_POST['setvisual'] = 1; }

	if (!($release_date = generate_release_date())) {
		$msg->addError('BAD_DATE');
	}

	if ($_POST['title'] == '') {
		$msg->addError('NO_TITLE');
	}
		
	if (!$msg->containsErrors()) {

		$_POST['title']     = $addslashes($_POST['title']);
		$_POST['body_text'] = $addslashes($_POST['body_text']);
		$_POST['keywords']  = $addslashes($_POST['keywords']);
		$_POST['keywords']  = $addslashes($_POST['keywords']);

		if ($_POST['cid']) {
			/* editing an existing page */
			$err = $contentManager->editContent($_POST['cid'], $_POST['title'], $_POST['body_text'], $_POST['keywords'], $_POST['new_ordering'], $_POST['related'], $_POST['formatting'], $_POST['new_pid'], $release_date);

			unset($_POST['move']);
			unset($_POST['new_ordering']);
			$cid = $_POST['cid'];
		} else {
			/* insert new */
			
			$inherit_release_date = 0; // for now.

			$cid = $contentManager->addContent($_SESSION['course_id'],
												  $_POST['new_pid'],
												  $_POST['new_ordering'],
												  $_POST['title'],
												  $_POST['body_text'],
												  $_POST['keywords'],
												  $_POST['related'],
												  $_POST['formatting'],
												  $release_date,
												  $inherit_release_date);
			$_POST['cid']    = $cid;
			$_REQUEST['cid'] = $cid;
		}
	}


	/* insert glossary terms */
	if (is_array($_POST['glossary_defs']) && ($num_terms = count($_POST['glossary_defs']))) {
		global $glossary, $glossary_ids, $msg;

		foreach($_POST['glossary_defs'] as $w => $d) {
			$old_w = $w;
			$w = urldecode($w);
			$key = in_array_cin($w, $glossary_ids);

			if (($key !== false) && (($glossary[$old_w] != $d) || isset($_POST['related_term'][$old_w])) ) {
				$w = $addslashes($w);
				$related_id = intval($_POST['related_term'][$old_w]);
				$sql = "UPDATE ".TABLE_PREFIX."glossary SET definition='$d', related_word_id=$related_id WHERE word_id=$key AND course_id=$_SESSION[course_id]";
				$result = mysql_query($sql, $db);
				$glossary[$old_w] = $d;
			} else if ($key === false) {
				$w = $addslashes($w);
				$related_id = intval($_POST['related_term'][$old_w]);
				$sql = "INSERT INTO ".TABLE_PREFIX."glossary VALUES (0, $_SESSION[course_id], '$w', '$d', $related_id)";
				$result = mysql_query($sql, $db);
				$glossary[$old_w] = $d;
			}
		}
	}

	if (!$msg->containsErrors() && $redir) {
		$_SESSION['save_n_close'] = $_POST['save_n_close'];

		$msg->addFeedback('CONTENT_UPDATED');
		header('Location: '.$_SERVER['PHP_SELF'].'?cid='.$cid.SEP.'close='.$_POST['save_n_close'].SEP.'tab='.$_POST['current_tab'].SEP.'setvisual='.$_POST['setvisual']);
		exit;
	} else {
		return;
	}
}

function generate_release_date($now = false) {
	if ($now) {
		$day  = date('d');
		$month= date('m');
		$year = date('Y');
		$hour = date('H');
		$min  = 0;
	} else {
		$day	= intval($_POST['day']);
		$month	= intval($_POST['month']);
		$year	= intval($_POST['year']);
		$hour	= intval($_POST['hour']);
		$min	= intval($_POST['minute']);
	}

	if (!checkdate($month, $day, $year)) {
		return false;
	}

	if (strlen($month) == 1){
		$month = "0$month";
	}
	if (strlen($day) == 1){
		$day = "0$day";
	}
	if (strlen($hour) == 1){
		$hour = "0$hour";
	}
	if (strlen($min) == 1){
		$min = "0$min";
	}
	$release_date = "$year-$month-$day $hour:$min:00";
	
	return $release_date;
}

function check_for_changes($row) {
	global $contentManager, $cid, $glossary, $glossary_ids_related, $addslashes;

	$changes = array();

	if ($row && strcmp(trim($addslashes($_POST['title'])), $row['title'])) {
		$changes[0] = true;
	} else if (!$row && $_POST['title']) {
		$changes[0] = true;
	}

	if ($row && strcmp(trim($addslashes($_POST['body_text'])), trim($row['text']))) {
		$changes[0] = true;
	} else if (!$row && $_POST['body_text']) {
		$changes[0] = true;
	}

	/* formatting: */
	if ($row && strcmp(trim($_POST['formatting']), $row['formatting'])) {
		$changes[0] = true;
	} else if (!$row && $_POST['formatting']) {
		$changes[0] = true;
	}

	/* release date: */
	if ($row && strcmp(substr(generate_release_date(), 0, -2), substr($row['release_date'], 0, -2))) {
		/* the substr was added because sometimes the release_date in the db has the seconds field set, which we dont use */
		/* so it would show a difference, even though it should actually be the same, so we ignore the seconds with the -2 */
		/* the seconds gets added if the course was created during the installation process. */
		$changes[1] = true;
	} else if (!$row && strcmp(generate_release_date(), generate_release_date(true))) {
		$changes[1] = true;
	}

	/* related content: */
	$row_related = $contentManager->getRelatedContent($cid);

	if (is_array($_POST['related']) && is_array($row_related)) {
		$sum = array_sum(array_diff($_POST['related'], $row_related));
		$sum += array_sum(array_diff($row_related, $_POST['related']));
		if ($sum > 0) {
			$changes[1] = true;
		}
	} else if (!is_array($_POST['related']) && !empty($row_related)) {
		$changes[1] = true;
	}

	/* ordering */
	if ($cid && isset($_POST['move']) && ($_POST['move'] != -1) && ($_POST['move'] != $row['content_parent_id'])) {
		$changes[1] = true;
	}

	if ($cid && (($_POST['new_ordering'] != $_POST['ordering']) || ($_POST['new_pid'] != $_POST['pid']))) {
		$changes[1] = true;
	}

	/* keywords */
	if ($row && strcmp(trim($_POST['keywords']), $row['keywords'])) {
		$changes[1] = true;
	}  else if (!$row && $_POST['keywords']) {
		$changes[1] = true;
	}


	/* glossary */
	if (is_array($_POST['glossary_defs'])) {
		global $glossary_ids;
		foreach ($_POST['glossary_defs'] as $w => $d) {

			$key = in_array_cin($w, $glossary_ids);
			if ($key === false) {
				/* new term */
				$changes[2] = true;
				break;
			} else if ($cid && ($d &&($d != $glossary[$glossary_ids[$key]]))) {
				/* changed term */
				$changes[2] = true;
				break;
			}
		}

		if (is_array($_POST['related_term'])) {
			foreach($_POST['related_term'] as $term => $r_id) {
				if ($glossary_ids_related[$term] != $r_id) {
					$changes[2] = true;
					break;
				}
			}
		}
	}
	
	return $changes;
}

function paste_from_file() {
	global $msg;
	if ($_FILES['uploadedfile']['name'] == '')	{
		$msg->addError('FILE_NOT_SELECTED');
		return;
	}
	if ($_FILES['uploadedfile']['name']
		&& (($_FILES['uploadedfile']['type'] == 'text/plain')
			|| ($_FILES['uploadedfile']['type'] == 'text/html')) )
		{

		$path_parts = pathinfo($_FILES['uploadedfile']['name']);
		$ext = strtolower($path_parts['extension']);

		if (in_array($ext, array('html', 'htm'))) {
			$_POST['body_text'] = file_get_contents($_FILES['uploadedfile']['tmp_name']);

			/* get the <title></title> of this page				*/

			$start_pos	= strpos(strtolower($_POST['body_text']), '<title>');
			$end_pos	= strpos(strtolower($_POST['body_text']), '</title>');

			if (($start_pos !== false) && ($end_pos !== false)) {
				$start_pos += strlen('<title>');
				$_POST['title'] = trim(substr($_POST['body_text'], $start_pos, $end_pos-$start_pos));
			}
			unset($start_pos);
			unset($end_pos);

			$_POST['body_text'] = get_html_body($_POST['body_text']); 

			$msg->addFeedback('FILE_PASTED');
		} else if ($ext == 'txt') {
			$_POST['body_text'] = file_get_contents($_FILES['uploadedfile']['tmp_name']);
			$msg->addFeedback('FILE_PASTED');

		}
	} else {
		$msg->addError('BAD_FILE_TYPE');
	}

	return;
}

//for accessibility checker
function write_temp_file() {
	global $_POST, $_base_href, $msg;

	$content_base = $_base_href . 'get.php/';

	if ($_POST['content_path']) {
		$content_base .= $_POST['content_path'] . '/';
	}

	$file_name = $_POST['cid'].'.html';

	if ($handle = fopen(AT_CONTENT_DIR . $file_name, 'wb+')) {
		$temp_content = '<h2>'.AT_print(stripslashes($_POST['title']), 'content.title').'</h2>';

		if ($_POST['body_text'] != '') {
			$temp_content .= format_content(stripslashes($_POST['body_text']), $_POST['formatting'], $_POST['glossary_defs']);
		}
		$temp_title = $_POST['title'];

		$html_template = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
			"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
		<head>
			<base href="{BASE_HREF}" />
			<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
			<title>{TITLE}</title>
			<meta name="Generator" content="ATutor accessibility checker file - can be deleted">
		</head>
		<body>{CONTENT}</body>
		</html>';

		$page_html = str_replace(	array('{BASE_HREF}', '{TITLE}', '{CONTENT}'),
									array($content_base, $temp_title, $temp_content),
									$html_template);
		
		if (!@fwrite($handle, $page_html)) {
			$msg->addError('FILE_NOT_SAVED');       
	   }
	} else {
		$msg->addError('FILE_NOT_SAVED');
	}
	$msg->printErrors();
}
?>