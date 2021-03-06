<?php

/* Copyright (c) 2009 National Research Institute for Earth Science and
 * Disaster Prevention (NIED).
 * This code is licensed under the GPL 3.0 license, availible at the root
 * application directory.
 */

require dirname(__FILE__). '/../../lib.php';

/* 振り分け*/
$eid = intval($_REQUEST["eid"]);
$pid = intval($_REQUEST["pid"]);

switch ($_REQUEST["action"]) {
	case 'regist':
		regist_data($eid, $pid);
	default:
		input_data($eid, $pid);
}

/* 登録*/
function regist_data($eid = null, $pid = null) {
	global $SYS_FORM;

	if ($eid > 0) {
		$id = $eid;
	}
	else if ($pid > 0) {
		$id = $pid;
	}
	else {
		show_error('選択元が不明です。');
	}

	if (!is_owner($id)) {
		die('You are not owner of '. $id);
	}

	// フォームのキャッシュに溜め込む
	$SYS_FORM["cache"]["subject"] = isset($_POST["subject"]) ? $_POST["subject"] : '無題';
	$SYS_FORM["cache"]["body"]    = $_POST["body"];
	if (intval($_POST["initymd_set"]) == 1) {
		$SYS_FORM["cache"]["initymd"]     = post2timestamp('initymd');
		$SYS_FORM["cache"]["initymd_set"] = 1;
	}
	else {
		$SYS_FORM["cache"]["initymd"] = date('Y-m-d H:i:s');
		$SYS_FORM["cache"]["initymd_set"] = 0;
	}

	// 入力エラーチェック
	if (!$SYS_FORM["cache"]["body"] || $SYS_FORM["cache"]["body"] == '<br />') {
		$SYS_FORM["error"]["body"] = '内容は何か書いてください。';
	}
	if ($SYS_FORM["error"]) {
		return;
	}
	// 登録
	$subject = htmlspecialchars($SYS_FORM["cache"]["subject"], ENT_QUOTES);
	$body    = $SYS_FORM["cache"]["body"];
	$initymd = $SYS_FORM["cache"]["initymd"];

	// pidはもう使いません。コード整理時に消します。
	if ($eid == 0) {
		$eid = get_seqid();

		$q = mysql_exec("insert into blog_data".
						" (id, pid, subject, body, initymd)".
					" values(%s, %s, %s, %s, %s)",
					mysql_num($eid), mysql_num($pid),
					mysql_str($subject), mysql_str($body), mysql_str($initymd));
	}
	else {
		$q = mysql_exec("update blog_data set subject = %s, body = %s, initymd = %s".
						" where id = %s",
						mysql_str($subject), mysql_str($body), mysql_str($initymd),
						mysql_num($eid));
	}

	if (!$q) {
		show_error('登録に失敗しました。'. mysql_error());
	}

//	set_keyword($eid);
	set_point($eid,$pid);
	set_pmt(array(eid => $eid, gid =>get_gid($pid), name => 'pmt_0'));

	$html = '編集完了しました。';
	$data = array(title   => 'ブログ編集完了',
				  icon    => 'finish',
				  content => $html. create_form_return(array(eid => $eid, href => home_url($eid))));

	show_input($data);

	exit(0);
}

function input_data($eid = null, $pid = null) {
	global $JQUERY, $SYS_INPUT_SCRIPT;

	// 親IDチェック
	if ($pid == 0) {
//		if (!($pid = get_pid($eid))) {
			show_error('パーツIDが不明です。');
//		}
	}

	$f = mysql_full("select rb.eid, rb.site_id, d.*, c.count, ra.display".
					" from blog_data as d".
					" inner join bosai_web_block as rb".
					" on rb.block_id = d.pid".
					" left join bosai_web_auth as ra".
					" on d.id = ra.id".
					" left join bosai_web_count as c".
					" on d.id = c.id".
					" where rb.eid = %s".
					" order by d.updymd desc",
					mysql_num($pid));
	
	if (!$f) {
		$f = array();
	}

	$list = array();

	$list[] = array(display => '状態',
					subject => '題名',
//					body    => '内容',
					updymd => '更新日時');

	$count = 0;
	$pre_count = 0;
	if ($f) {
		while ($r = mysql_fetch_array($f)) {
			$href = "/index.php?module=bosai_web&eid=$r[id]&blk_id=$pid";
			$subject = $r['subject'] ? $r['subject'] : '無題';
			$c = mysql_uniq('select * from bosai_web_count where id= %s', mysql_num($r['id']));
			if ($c) {
				$count = $c['count'];
			}
			else {
				$count = 1;
			}
			switch (intval($r['display'])) {
				case '2':
					$subject .= ' (第'. $count. '報)';
					$display = '<div style="white-space: nowrap; color: #8abfd6;">承認済</div>';
					break;
				case '1':
					$display = '<div style="white-space: nowrap; color: #fca890;">承認待ち</div>';
					break;
				default:
					$display = '<div style="white-space: nowrap; color: #999;">編集中</div>';
			}
			$sitename = get_site_name($r['site_id']);
//			$sitehref = '/location.php?eid='. $r['site_id'];
			$sitehref = '/index.php?site_id='. $r['site_id'];
			$list[] = array(id      => $r['id'],
							display => $display,
//							subject => make_href($subject, $href, null, '_blank', 48).
							subject => clip_str($subject, 48).
									   '<div style="text-align: right;">from '.
									   make_href($sitename, $sitehref, null, '_blank', 48).
									   '</div>',
//							body    => clip_str($r['body'], 50),
							updymd  => date('Y年m月d日 H時i分', tm2time($r['updymd'])));
		}
	}

	set_return_url();

	$editor = array('校正/承認' => "/index.php?module=bosai_web&blk_id=$pid&eid=");

	$html = create_auth_list($editor, $list);

	$data = array(title   => 'ブログの編集',
				  icon    => 'write',
				  content => $html);

	show_input($data);

	exit(0);
}

?>
