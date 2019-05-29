#!/usr/bin/perl

use strict;
use warnings;
use CGI;

our $filename = `basename $0`;
chomp $filename;
our $product = `cat /var/lib/model | grep '^product	' | cut -f 2`;
chomp $product;

sub __print_header() {
	print <<EOF;
Content-type: text/html
Pragma: no-cache
Cache-Control: no-cache
Expires: Thu, 01 Dec 1994 16:00:00 GMT

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta HTTP-EQUIV="Cache-Control" content="no-cache">
<meta HTTP-EQUIV="Pragma" CONTENT="no-cache">
<html>
<head><title>初期設定PINコードの登録</title></head>
<body>
EOF
}

sub __print_footer() {
	print <<EOF;
</body>
</html>
EOF
}

sub __print_booting() {
	print <<EOF;
起動中...
<p>
EOF
}

sub __print_registered($) {
	my $url = shift;
	print <<EOF;
登録しました。
<p>
EOF
	if ($url) {
		print <<EOF;
<a href="$url">→戻る</a>
<p>
EOF
	}
}

sub __print_already_registered() {
	print <<EOF;
既に登録されています。
<p>
EOF
}

sub __print_err_route() {
	print <<EOF;
<font color='red'>インターネットに接続できません。</font>
<p>
EOF
}

sub __print_err_regist($) {
	my $regist_log = shift;
	print <<EOF;
<font color='red'>登録に失敗しました。</font>
<p>
EOF
	if ( -f "/etc/beta" and -f $regist_log ) {
		print "<pre>";
		print `cat $regist_log`;
		print "</pre>";
	}
}

sub __print_err_rl3fmt() {
	print <<EOF;
<font color='red'>QRコードのフォーマットが不正です。</font>
<p>
EOF
}

sub __print_err_pincode() {
	print <<EOF;
<font color='red'>PINコードが不正です。</font>
<p>
EOF
}

sub __print_err_pincode_length() {
	print <<EOF;
<font color='red'>PINコードの長さが不正です。</font>
<p>
EOF
}

sub __print_err_user() {
	print <<EOF;
<font color='red'>ユーザ名が不正です。</font>
<p>
EOF
}

sub __print_err_pass() {
	print <<EOF;
<font color='red'>パスワードが不正です。</font>
<p>
EOF
}

sub __print_err_oldsheet() {
	print <<EOF;
<font color='red'>設定シートが旧版です。</font>
<p>
EOF
}

sub __print_err_devname() {
	print <<EOF;
<font color='red'>設定シートが本製品用ではありません。</font>
<p>
EOF
}

sub __print_textarea($) {
	my $url = shift;
	print <<EOF;
テキストエリアにQRコードリーダで読み取ってください。
<p>
<form method="post" action="$filename">
<input type="hidden" name="url" value="$url">
<textarea name="qrcodefmt" cols="128" rows="5"></textarea>
<p>
<input type="submit" value="初期設定PINコードを登録する">
<p>
</form>
EOF
}

sub __get_value($$) {
	my $fmt = shift;
	my $key = shift;
	my $line = `echo '$fmt' | grep '^$key='`;
	chomp $line;
	if (!$line) {
		return undef;
	}
	my $value = `echo '$line' | sed 's/^$key=//'`;
	chomp $value;
	return $value;
}

sub __get_rl3fmt($) {
	my $qrcodefmt = shift;
	my $data = __get_value($qrcodefmt,"DATA");
	if (!$data) {
		return undef;
	}
	my $rl3fmt = `echo '$data' | /usr/local/rl3/bin/cryptfmt.py decode | tr -d '\r'`;
	return $rl3fmt;
}

sub __get_pincode($) {
	return __get_value(shift,"PINCODE");
}

sub __get_user($) {
	return __get_value(shift,"RL3USER");
}

sub __get_pass($) {
	return __get_value(shift,"RL3PASS");
}

sub __get_devname($) {
	return __get_value(shift,"DEVNAME");
}

sub __check_route() {
	if ( system("wget http://www.iodata.jp/ -O - >/dev/null 2>&1") != 0 ) {
		return 0;
	}
	return 1;
}

sub __regist_init($$) {
	my $result = 0;
	my $pincode = shift;
	my $regist_log = shift;
	my $tou_server = 0;
	if ( system("pgrep tou_server >/dev/null 2>&1") == 0 ) {
		$tou_server = 1;
	}
	if ( ! $tou_server ) {
		print "デバイスサーバーライブラリを起動しています...<p>";
		if ( system("sudo /etc/init.d/rl3.sh start > /dev/null 2>&1") != 0 ) {
			return 0;
		}
	}
	print "PINコードを登録しています...<p>";
	if ( system("sudo /usr/local/rl3/bin/pincode.sh regist_init_kensa $pincode > $regist_log 2>&1") == 0 ) {
		$result = 1;
	}
	if ( ! $tou_server ) {
		print "デバイスサーバーライブラリを停止しています...<p>";
		if ( system("sudo /etc/init.d/rl3.sh stop > /dev/null 2>&1") != 0 ) {
			return 0;
		}
	}
	return $result;
}

# main

__print_header();

if ( ! -f '/var/lock/lanicn/.boot-done' ) {
	__print_booting();
	__print_footer();
	exit(1);
}

if ( system("/usr/local/rl3/bin/pincode.sh init > /dev/null 2>&1") == 0 ) {
	__print_already_registered();
	__print_footer();
	exit(1);
}

my $cgi = new CGI;
my $url = $cgi->param('url');
if ($ENV{'REQUEST_METHOD'} eq 'POST') {
	my $qrcodefmt = $cgi->param('qrcodefmt');
	my $rl3fmt = __get_rl3fmt($qrcodefmt);
	if (!defined($rl3fmt)) {
		__print_err_rl3fmt();
		__print_textarea($url);
		__print_footer();
		exit(1);
	}
	print <<EOF;
<pre>
$rl3fmt
</pre>
EOF
	my $pincode = __get_pincode($rl3fmt);
	my $user = __get_user($rl3fmt);
	my $pass = __get_pass($rl3fmt);
	my $devname = __get_devname($rl3fmt);
	if (!defined($pincode)) {
		__print_err_pincode();
		__print_textarea($url);
		__print_footer();
		exit(1);
	}
	if (length($pincode) != 32) {
		__print_err_pincode_length();
		__print_textarea($url);
		__print_footer();
		exit(1);
	}
	if (!defined($user) or $user ne 'remote') {
		__print_err_user();
		__print_textarea($url);
		__print_footer();
		exit(1);
	}
	if (!defined($pass) or $pass ne '') {
		__print_err_pass();
		__print_textarea($url);
		__print_footer();
		exit(1);
	}
	if (!defined($devname)) {
		__print_err_oldsheet();
		__print_textarea($url);
		__print_footer();
		exit(1);
	}
	if ($devname ne $product) {
		__print_err_devname();
		__print_textarea($url);
		__print_footer();
		exit(1);
	}
	my $regist_log = "/tmp/regist.log";
	system("sudo rm $regist_log");
	if ( ! __regist_init($pincode,$regist_log) ) {
		if ( ! __check_route() ) {
			__print_err_route();
			__print_footer();
			exit(1);
		}
		__print_err_regist($regist_log);
		__print_footer();
		exit(1);
	}
	__print_registered($url);
	__print_footer();
	exit(0);
}

__print_textarea($url);
__print_footer();
exit(0);
