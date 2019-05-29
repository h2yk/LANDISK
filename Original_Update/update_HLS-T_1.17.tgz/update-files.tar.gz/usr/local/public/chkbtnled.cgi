#!/usr/bin/perl

use strict;
use warnings;
use CGI;

our $filename = `basename $0`;
chomp $filename;
our $pcb = `cat /var/lib/model | grep '^pcb	' | cut -f 2`;
chomp $pcb;

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
<head><title>ボタン検査</title></head>
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

sub __print_already_checked() {
	print <<EOF;
既に検査されています。
<p>
EOF
}

sub __print_err() {
	print <<EOF;
<font color='red'>ボタン検査デーモンを起動できませんでした</font>
EOF
}

sub __print_textarea($) {
	my $url = shift;
	if ($pcb eq "HLS-PG") {
		print <<EOF;
エラーLED、通知LED、メディアLEDを点灯させています。<br>
エラーLED、通知LED、メディアLEDが点灯していることを確認してください。
<p>
まず、電源ボタン、リセットボタンを押してLEDが消えることを確認してください。<br>
※1秒以上押さないでください。
<p>
次にSDカードスロットにSDカードを挿入し、メディアLEDが点滅することを確認してください。<br>
<p>
次にSDカードスロットにSDカードを抜き、メディアLEDが消えることを確認してください。<br>
EOF
	} elsif ($pcb eq "HLS-C") {
		print <<EOF;
エラーLED、通知LEDを点灯させています。<br>
エラーLED、通知LEDが点灯していることを確認してください。
<p>
電源ボタン、リセットボタンを押してLEDが消えることを確認してください。<br>
※1秒以上押さないでください。
EOF
	} else {
		print <<EOF;
LEDを赤点灯させています。<br>
LEDが赤点灯していることを確認してください。<br>
<p>
まず電源ボタンを押してLEDが橙点灯となることを確認してください。<br>
※1秒以上押さないでください。
<p>
次にリセットボタンを押してLEDが緑点灯となることを確認してください。<br>
※1秒以上押さないでください。
EOF
	}
	print <<EOF;
<p>
<a href="$url">→戻る</a>
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

# main

__print_header();

if ( ! -f '/var/lock/lanicn/.boot-done' ) {
	__print_booting();
	__print_footer();
	exit(1);
}

if ( -f '/boot/.button_checked' ) {
	__print_already_checked();
	__print_footer();
	exit(0);
}

my $cgi = new CGI;
my $url = $cgi->param('url');
system("sudo su -c 'pgrep -f /usr/local/bin/chkbtnled.sh | xargs -n 1 kill'");
if ( system("sudo su -c 'daemonize /usr/local/bin/chkbtnled.sh'") != 0 ) {
	__print_err();
	__print_footer();
	exit(1);
}
__print_textarea($url);
__print_footer();
exit(0);
