#!/usr/bin/perl
# info.cgi: 検査用 CGI
# Copyright (C) 2007-2017 I-O DATA DEVICE, INC.  ALL RIGHTS RESERVED.
#

# /etc/apache2/apache.conf
# AddHandler chi-script.cgiをコメントアウトしなくてはいけない
# SMARTチェックは？

use strict;
use warnings;
use bigint;

sub __debug {
	my ($msg) = @_;
#	system("sudo echo '$msg' >> /tmp/info.cgi.debug.log");
}

sub get_model_info {
	my ($info) = @_;
	__debug("get_model_info($info);");

	my $value = `grep "^$info\t" /var/lib/model | sed 's/$info\t//'`;
	chomp $value;
	return $value;
}

sub get_version_uboot {
	__debug("get_version_uboot();");

	my $value = `dd if=/dev/mtdblock0 | strings | grep '^U-Boot .* MV : .* I-O : ' | sed 's/^U-Boot \\(.*\\) (\\(.*\\)) MV : \\(.*\\) I-O : \\(.*\\)/\\4 (\\2)/'`;
	chomp $value;
	return $value;
}

# ディスク容量の上下限
my %capalimit = (
	'HLS-C' => {
		"500" => { floor => 450, ceil => 500, },
		"1.0" => { floor => 900, ceil => 1000, },
		"1.5" => { floor => 1350, ceil => 1500, },
		"2.0" => { floor => 1800, ceil => 2000, },
		"3.0" => { floor => 2700, ceil => 3000, },
		"4.0" => { floor => 3600, ceil => 4000, },
		"5.0" => { floor => 4500, ceil => 5000, },
		"6.0" => { floor => 5400, ceil => 6001, },
	},
	'HLS-T' => {
		"500" => { floor => 450, ceil => 500, },
		"1" => { floor => 900, ceil => 1000, },
		"2" => { floor => 1800, ceil => 2000, },
		"3" => { floor => 2700, ceil => 3000, },
		"4" => { floor => 3600, ceil => 4000, },
		"5" => { floor => 4500, ceil => 5000, },
		"6" => { floor => 5400, ceil => 6001, },
	},
	'HDL-T' => {
		"500" => { floor => 450, ceil => 500, },
		"1" => { floor => 900, ceil => 1000, },
		"2" => { floor => 1800, ceil => 2000, },
		"3" => { floor => 2700, ceil => 3000, },
		"4" => { floor => 3600, ceil => 4000, },
		"5" => { floor => 4500, ceil => 5000, },
		"6" => { floor => 5400, ceil => 6001, },
	},
	'HLS-PG' => {
		"500" => { floor => 450, ceil => 500, },
		"1" => { floor => 900, ceil => 1000, },
		"2" => { floor => 1800, ceil => 2000, },
		"3" => { floor => 2700, ceil => 3000, },
		"4" => { floor => 3600, ceil => 4000, },
		"5" => { floor => 4500, ceil => 5000, },
		"6" => { floor => 5400, ceil => 6001, },
	},
);


# HDD モデルと容量
my %product_full2idema = (
	'HLS-C500' => 0x3A386030,
	'HLS-C1.0' => 0x74706DB0,
	'HLS-C1.5' => 0xAEA87B30,
	'HLS-C2.0' => 0xE8E088B0,
	'HLS-C3.0' => 0x15D50A3B0,
	'HLS-C4.0' => 0x1D1C0BEB0,
	'HLS-C5.0' => 0x24630D9B0,
	'HLS-C6.0' => 0x2BAA0F4B0,
	'HLS-C8.0' => 0x3A3812AB0,
	'HLS-C500HF' => 0x3A386030,
	'HLS-C1.0HF' => 0x74706DB0,
	'HLS-C1.5HF' => 0xAEA87B30,
	'HLS-C2.0HF' => 0xE8E088B0,
	'HLS-C3.0HF' => 0x15D50A3B0,
	'HLS-C4.0HF' => 0x1D1C0BEB0,
	'HLS-C5.0HF' => 0x24630D9B0,
	'HLS-C6.0HF' => 0x2BAA0F4B0,
	'HLS-C8.0HF' => 0x3A3812AB0,
	'HLS-C500SHF' => 0x3A386030,
	'HLS-C1.0SHF' => 0x74706DB0,
	'HLS-C1.5SHF' => 0xAEA87B30,
	'HLS-C2.0SHF' => 0xE8E088B0,
	'HLS-C3.0SHF' => 0x15D50A3B0,
	'HLS-C4.0SHF' => 0x1D1C0BEB0,
	'HLS-C5.0SHF' => 0x24630D9B0,
	'HLS-C6.0SHF' => 0x2BAA0F4B0,
	'HLS-C8.0SHF' => 0x3A3812AB0,
	'HDL-T500' => 0x3A386030,
	'HDL-T1' => 0x74706DB0,
	'HDL-T2' => 0xE8E088B0,
	'HDL-T3' => 0x15D50A3B0,
	'HDL-T4' => 0x1D1C0BEB0,
	'HDL-T5' => 0x24630D9B0,
	'HDL-T6' => 0x2BAA0F4B0,
	'HDL-T8' => 0x3A3812AB0,
	'HDL-TC500' => 0x3A386030,
	'HDL-TC1' => 0x74706DB0,
	'HDL-TC2' => 0xE8E088B0,
	'HDL-TC3' => 0x15D50A3B0,
	'HDL-TC4' => 0x1D1C0BEB0,
	'HDL-TC5' => 0x24630D9B0,
	'HDL-TC6' => 0x2BAA0F4B0,
	'HDL-TC8' => 0x3A3812AB0,
	'HDL-T1SLD' => 0x74706DB0,
	'HDL-T2SLD' => 0xE8E088B0,
	'HDL-T3SLD' => 0x15D50A3B0,
	'HDL-T4SLD' => 0x1D1C0BEB0,
	'HDL-T5SLD' => 0x24630D9B0,
	'HDL-T6SLD' => 0x2BAA0F4B0,
	'HDL-T8SLD' => 0x3A3812AB0,
	'HLS-PG500' => 0x3A386030,
	'HLS-PG1' => 0x74706DB0,
	'HLS-PG2' => 0xE8E088B0,
	'HLS-PG3' => 0x15D50A3B0,
	'HLS-PG4' => 0x1D1C0BEB0,
	'HLS-PG5' => 0x24630D9B0,
	'HLS-PG6' => 0x2BAA0F4B0,
	'HLS-PG8' => 0x3A3812AB0,
);

###############################################################################
# 容量付きモデル名を推測
#  guess_product_full()
###############################################################################
sub guess_product_full
{
	my ($product, $model, $submodel, $subsubmodel, $capa_gb1, $type) = @_;
	__debug("guess_product_full($product,$model,$submodel,$subsubmodel,$capa_gb1,$type);");

	my $product_full = undef;

	my $capa_gb = $capa_gb1;
	if ( exists $capalimit{$model} )
	{
		while ( my ( $k, $v ) = each %{$capalimit{$model}} )
		{
			if ( ($v->{floor} <= $capa_gb) 
				&& ($capa_gb <= $v->{ceil}) ) 
			{
				$product_full = "${model}${submodel}${k}${type}${subsubmodel}";
				last;
			}
		}
	}
	return $product_full;
}


###############################################################################
#  get_smart(hda:hdb:hdc:hdd)
###############################################################################
sub get_smart
{
	my ($dev) = @_;
	__debug("get_smart($dev);");

	my $dn =qq|<font color="#ff0000">ＮＧ！ドライブが認識できません</font>|;
	my $sn ="";
	my $fv ="";
	my $capa ="";
	my $type ="";
	my $device ="/dev/";

	$device .= $dev;

	system("sudo /usr/local/sbin/smartctl6 -d marvell -a $device > /tmp/outputfile");

	open(READ,"/tmp/outputfile");
	my @tmp = ();
	my $line = '';

	while($line=<READ>){
		if($line =~ /^Device\sModel/){
			@tmp = split(/\:/,$line);
			$dn =$tmp[1];
			chomp $dn;
		}
		if($line =~ /^Serial\sNumber/){
			@tmp = split(/\:/,$line);
			$sn =$tmp[1];
			chomp $sn;
		}
		if($line =~ /^Firmware\sVersion/){
			@tmp = split(/\:/,$line);
			$fv =$tmp[1];
			chomp $fv;
		}
		if($line =~ /^User\sCapacity/){
			@tmp = split(/\:/,$line);
			$capa =$tmp[1];
			chomp $capa;
		}
		if($line =~ /^Rotation\sRate:\s*Solid State Device/){
			$type = "S";
		}
	}
	close(READ);
	system("sudo rm -f /tmp/outputfile");

	return("$dn","$sn","$fv","$capa","$type");
}

###############################################################################
#  get_macaddress()
###############################################################################
sub get_macaddress()
{
	system("sudo /sbin/ifconfig > /tmp/outputfile");
	open(READ,"/tmp/outputfile");
	my @tmp = ();
	my $mac = '';
	my $line = '';

	while($line=<READ>){
		if($line =~ /^eth0/){
			@tmp = split(/HWaddr/,$line);
			$mac = $tmp[1];
		}
	}
	close(READ);
	system("sudo rm -f /tmp/outputfile");

	return("$mac");
}

###############################################################################
#  get_pincode()
###############################################################################
sub get_pincode()
{
	my $result = system("sudo /usr/local/bin/rl3_pincode.sh init > /tmp/pincode_init");
	if ($result != 0) {
		return("<font color='red'>登録してください</font><a href='/rl3/regist.cgi?url=/info.cgi'>→登録する</a>");
	}
	$result = system("sudo /usr/local/bin/rl3_pincode.sh current > /tmp/pincode_current");
	if ($result != 0) {
		return("登録されていません(current)");
	}
	$result = system("sudo cmp /tmp/pincode_init /tmp/pincode_current");
	if ($result != 0) {
		return("PINコードが変更されています");
	}
	
	open(READ,"/tmp/pincode_init");
	my $pincode = '';
	my $line = '';
	while($line=<READ>){
		$pincode = $line;
	}
	close(READ);
	system("sudo rm -f /tmp/pincode_init");
	system("sudo rm -f /tmp/pincode_current");

	return("$pincode");
}

###############################################################################
#  chk_pincode()
###############################################################################
sub chk_pincode {
	my ($pincode) = @_;
	__debug("chk_pincode($pincode);");

	my $result = '';
	if ($pincode !~ '登録してください') {
		my $pincode_current = `sudo /usr/local/bin/rl3_pincode.sh current 2>/dev/null`;
		if ($pincode ne $pincode_current) {
			$result = '異常です';
		}
	}

	return $result;
}


###############################################################################
#  get_button()
###############################################################################
sub get_button()
{
	if ( ! -f "/boot/.button_checked" ) {
		return("<font color='red'>検査してください</font><a href='/chkbtnled.cgi?url=/info.cgi'>→検査する</a>");
	}
	return("検査済みです");
}

###############################################################################
#  chk_button()
###############################################################################
sub chk_button
{
	my ($button) = @_;
	__debug("chk_button($button);");

	my $result = '';
	if ($button !~ '検査してください') {
		if ($button ne "検査済みです") {
			$result = '異常です';
		}
	}

	return $result;
}

###############################################################################
#  get_users()
###############################################################################
sub get_users()
{
	my $users = `/mnt/hda5/bin/commonconfig.php 'user' 'ユーザ情報' | tr -d '\\n' | sed 's/ *, */\\n/g' | sed 's/.*\\[name:\\(.*\\)\\]/\\1/g' | tr '\\n' ','`;
	return($users);
}

###############################################################################
#  chk_users()
###############################################################################
sub chk_users
{
	my ($users) = @_;
	__debug("chk_users($users);");

	my $result = '';
	my @list = split(/,/, $users);
	foreach my $user (@list) {
		if ( system("grep '$user' /etc/passwd") != 0 ) {
			$result = '異常です';
		}
	}
	return $result;
}

###############################################################################
#  get_shares()
###############################################################################
sub get_shares()
{
	my $shares = `/mnt/hda5/bin/commonconfig.php 'share' '共有フォルダ情報' | sed 's/\\(\\[name:\\)/\\n\\1/g' | grep '^\\[name:' | sed 's/^\\[name:\\([A-Za-z0-9_]*\\), .*/\\1/g' | tr '\\n' ',' | sed 's/,\$//g'`;
	return($shares);
}

###############################################################################
#  chk_shares()
###############################################################################
sub chk_shares
{
	my $product = shift;
	my ($shares) = @_;
	__debug("chk_shares($product, $shares);");

	my $result = '';
	my @list = split(/,/, $shares);
	foreach my $share (@list) {
		if ( ! -d "/mnt/sataraid1/share/${share}" ) {
			$result = "異常です:${share}が存在しません";
		}
		my $filecount = `ls -a /mnt/sataraid1/share/${share} | wc -l`;
		if ( "$product" eq "HLS-CHF" and "${share}" eq "disk" ) {
			if ( $filecount != 3 ) {
				$result = "異常です:${share}のファイル数";
			}
			my $filecount = `ls -a /mnt/sataraid1/share/${share}/music | wc -l`;
			if ( $filecount != 2 ) {
				$result = "異常です:${share}/musicのファイル数";
			}
		} else {
			if ( $filecount != 2 ) {
				$result = "異常です:${share}のファイル数";
			}
		}
	}
	return $result;
}

###############################################################################
# table_header
###############################################################################
sub table_header {
	my ($title) = @_;
	__debug("table_header($title);");

	print <<EOM;
<table width="550" style="font-size: 14pt">
<tr><td><center>${title}</center></td></tr>
</table>
<table border width="550">
EOM
}
###############################################################################
# table_footer
###############################################################################
sub table_footer() {
	print <<EOM;
</table>
EOM
}
###############################################################################
# 2 列のテーブル
###############################################################################
sub table_2 {
	my ($parity, $width_row1, $message_row1, $message_row2, $message_result, $id) = @_;
	__debug("table_2($parity,$width_row1,$message_row1,$message_row2,$message_result,$id);");

	my $width_row2 = 100 - $width_row1;
	my $bgcolor_column = '';

	if ($parity == 0) {
		$bgcolor_column = '#f7f7f7';
	} elsif ($parity == 1) {
		$bgcolor_column = '#f4f0e7';
	} else {
		$bgcolor_column = "silver";
	}

	if($message_result ne '') {
		$message_result = " ($message_result)";
	}

	# 入力値の先頭空白文字と末尾空白文字を取り除く
	#$message_row1 =~ s/^\s+//;
	#$message_row1 =~ s/\s+$//;
	#$message_row2 =~ s/^\s+//;
	#$message_row2 =~ s/\s+$//;

	print <<EOM;
<tr bgcolor="${bgcolor_column}" id="${id}">
<td width="${width_row1}%">${message_row1}</td>
<td width="${width_row2}%">${message_row2}${message_result}</td>
</tr>
EOM
	return ($parity == 0) ? 1 : 0;
}


###############################################################################
# システム時刻を取得
###############################################################################
sub get_system_time() {
        my $year = 0;
        my $month = 0;
        my $day = 0;
        my $hour = 0;
        my $minute = 0;
        my $second = 0;

        open(TIME,"date +%Y,%m,%d,%H,%M,%S|") or die "Can't run program: $!\n";
        my $buf = <TIME>;
        close(TIME);

        chomp($buf);
        ($year, $month, $day, $hour, $minute, $second) = split(/\,/, $buf);
	chomp($year);
	chomp($month);
	chomp($day);
	chomp($hour);
	chomp($minute);
	chomp($second);

	return "$year 年 $month 月 $day 日 $hour 時 $minute 分 $second 秒";
}

###############################################################################
# ボリュームの容量を取得
###############################################################################
sub get_block_size {
	my ($dev) = @_;
	__debug("get_block_size($dev);");

	my $file = "/sys/block/$dev/size";
	if ( ! -e "$file" ) {
		return undef;
	}
	my $size = `cat $file`;
	chomp $size;

	return $size;
}

###############################################################################
# ボリュームの容量をチェック
###############################################################################
sub chk_vol_block_size {
	my ($vol_block_size) = @_; # セクタ数
	__debug("chk_vol_block_size($vol_block_size);");

	my $result = '';
	if ( $vol_block_size < 20000000 ) { # 10G
		$result = 'サイズが異常に小さい';
	}

	return $result;
}

###############################################################################
# 製品名をチェック
###############################################################################
sub chk_product {
	my ($product_full, $guessed_product_full) = @_;
	__debug("chk_product($product_full,$guessed_product_full);");

	my $result = '';
	if ( ! defined $guessed_product_full ) {
		$result = "モデル名不明、容量異常です";
	} elsif ( ! ( $product_full =~ /^$guessed_product_full/ ) ) {
		$result = "推測した製品型番($guessed_product_full)と異なる";
	}

	return $result;
}

###############################################################################
# ブートローダバージョンをチェック
###############################################################################
sub chk_version_uboot {
	my ($pcb,$version) = @_;
	__debug("chk_version_uboot($pcb,$version);");

	my $result = '';
	# 現時点ではPCBに関係なく同じ
	if ($version eq '1.09 (Mar 31 2017 - 11:06:39)') {
	} else {
		$result = '異常です';
	}

	return $result;
}

###############################################################################
# システムバージョンをチェック(BETAでないかどうかだけ)
###############################################################################
sub chk_version {
	my ($beta) = @_;
	__debug("chk_version($beta);");

	my $result = '';
	if ( $beta ) {
		$result = "評価用($beta)";
	}

	return $result;
}

###############################################################################
# idema値をチェック
###############################################################################
sub chk_idema_block_size {
	my ($product_full, $block_size) = @_;
	__debug("chk_idema_block_size($product_full,$block_size);");

	my $result = '';
	if ( defined $product_full 
		&& exists($product_full2idema{$product_full}) ) {
		if ($block_size != $product_full2idema{$product_full} ) {
			$result = 'IDEMA値でない/製品型番に対して異なる値';
		}
	} else {
		$result = '容量テーブルに製品型番が存在しません';
	}

	return $result;
}

###############################################################################
# MACアドレス内容チェック
###############################################################################
sub chk_macaddress {
	my ($macaddress) = @_;
	__debug("chk_macaddress($macaddress);");

	my $result = '';
	$macaddress =~ s/^\s+//;
	$macaddress =~ s/\s+$//;

	#00:A0:B0または34:76:C5で始まる事！
	my @macaddrlist = split(/\:/,$macaddress);
	if(     (          !($macaddrlist[0] eq "00")
			or !($macaddrlist[1] eq "A0")
			or !($macaddrlist[2] eq "B0"))
		and (      !($macaddrlist[0] eq "34")
			or !($macaddrlist[1] eq "76")
			or !($macaddrlist[2] eq "C5"))
	)
	{
		$result = '異常です';
	}
	return $result;
}

###############################################################################
#メイン
###############################################################################

# NASブートローダVersionの取得
my $version_uboot = get_version_uboot();

# NASファームウェアVersionの取得
my $pcb = get_model_info('pcb');
my $model = get_model_info('model');
my $submodel = get_model_info('submodel');
my $subsubmodel = get_model_info('subsubmodel');
my $product = get_model_info('product');
my $product_full = get_model_info('product_full');
my $firmware = get_model_info('firmware');
my $version = get_model_info('version');
my $beta = get_model_info('beta');

# システム時刻取得
my $system_time = get_system_time();

# ドライブのモデル・容量取得
my $dev_sata1 = 'sda';
my ($dn1,$sn1,$fv1,$capa1,$type) = get_smart($dev_sata1);
my $block_size1 = get_block_size($dev_sata1);
my $capa_gb1 = sprintf('%llu',$block_size1 /125 /125 /125);

my $vol_block_size = get_block_size("$dev_sata1/${dev_sata1}6");
my $vol_capa_gb = sprintf('%llu',$vol_block_size /125 /125 /125);

my $macaddress = get_macaddress();

my $pincode = get_pincode();
my $button = get_button();
my $users = get_users();
my $shares = get_shares();

my $guessed_product_full = guess_product_full($product,$model,$submodel,$subsubmodel,$capa_gb1,$type);

# チェック
my $product_result = chk_product($product_full, $guessed_product_full);
my $version_uboot_result = chk_version_uboot($pcb, $version_uboot);
my $version_result = chk_version($beta);
my $vol_capa_gb_result = chk_vol_block_size($vol_block_size);
my $capa_gb1_result = chk_idema_block_size($product_full, $block_size1);
my $macaddress_result = chk_macaddress($macaddress);
my $pincode_result = chk_pincode($pincode);
my $button_result = chk_button($button);
my $users_result = chk_users($users);
my $shares_result = chk_shares($product, $shares);

print <<EOM;
Content-type: text/html
Pragma: no-cache
Cache-Control: no-cache
Expires: Thu, 01 Dec 1994 16:00:00 GMT

EOM

print <<EOM;
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta HTTP-EQUIV="Cache-Control" content="no-cache">
<meta HTTP-EQUIV="Pragma" CONTENT="no-cache">
<html>
<head>
<title>$model series - I-O DATA HLS-C</title>
EOM

if ( ! -f '/var/lock/lanicn/.boot-done' ) {
	print <<EOM;
</head>
<body>起動中...</body>
</html>
EOM
	exit(1);
}

my $t = time;	# NASの時刻
my $difflimit = 180;	# 時計の差の上限

print <<EOM;
<script LANGUAGE="JavaScript">

	function ng(id)
	{
		document.getElementById('body').style.backgroundColor = 'red';
		document.getElementById(id).style.color = 'red';
	}

        /*
         * Web 端末時刻
         */
        function pc_time()
        {
            var date = new Date();

            var y = date.getFullYear();
            var m = date.getMonth()+1;
            var d = date.getDate();
            var hh = date.getHours();
            var mm = date.getMinutes();
            var ss = date.getSeconds();
            if ( m < 10 ) m = "0" + m;
            if ( d < 10 ) d = "0" + d;
            if ( hh < 10 ) hh = "0" + hh;
            if ( mm < 10 ) mm = "0" + mm;
            if ( ss < 10 ) ss = "0" + ss;

            document.write(y+" 年 "+m+" 月 "+d+" 日 "+hh+" 時 "+mm+" 分 "+ss+" 秒");
        }

         /*
         * HDL-G* 時刻と端末時刻の差を返す
         */
        function diff_time()
        {
            var date0 = new Date();     // Web 端末の時計

            var date1 = new Date();     // HDL-G* の時計
            date1.setTime($t*1000);

            var d = (date1.getTime() - date0.getTime())/1000;
            return Math.round(d);
        }


        /*
         * 時刻の差が基準値以上ならば，赤文字で表示
         */
        function warn_time()
        {
            var d = diff_time();

            if ( (-$difflimit <= d) && ( d <= $difflimit) ) {
                // OK
            } else {
                // NG
		ng('system_time');
		ng('pc_time');
            }
        } 

	/*
	 * 結果が空でない場合は赤文字で表示
	 */
	function warn_result(result, id)
	{
		if (result) {
			ng(id);
		}
	}

	function warn()
	{
		warn_time();
		warn_result("$product_result", 'product');
		warn_result("$version_uboot_result", 'version_uboot');
		warn_result("$version_result", 'version');
		warn_result("$vol_capa_gb_result", 'vol_capa_gb');
		warn_result("$capa_gb1_result", 'capa_gb1');
		warn_result("$macaddress_result", 'macaddress');
		warn_result("$pincode_result", 'pincode');
		warn_result("$button_result", 'button');
		warn_result("$users_result", 'users');
		warn_result("$shares_result", 'shares');
	}
</script>
</head>
<body id='body' onload="warn()">
<center>
<p>
EOM

&table_header("$model series System infomation");

my $p=0;
$p = &table_2($p,30 ,"基板名",$pcb,'','pcb');
$p = &table_2($p,30 ,"モデル名",$model,'','model');
$p = &table_2($p,30 ,"製品名",$product,$product_result,'product');
$p = &table_2($p,30 ,"製品型番",$product_full,'','product_full');
$p = &table_2($p,30 ,"ブートローダバージョン" ,$version_uboot,$version_uboot_result,'version_uboot');
$p = &table_2($p,30 ,"システムバージョン" ,$version,$version_result,'version');
$p = &table_2($p,30 ,"システム時刻"	,$system_time,'','system_time');
$p = &table_2($p,30 ,"端末時刻"		,qq|<script LANGUAGE="JavaScript">pc_time();</script>|,'','pc_time');
$p = &table_2($p,30 ,"MACアドレス"	,$macaddress,$macaddress_result,'macaddress');
my $result = system("/usr/local/bin/issolidmodel.sh");
if ($result != 0) {
	$p = &table_2($p,30 ,"PINコード"	,$pincode,$pincode_result,'pincode');
}
$p = &table_2($p,30 ,"全容量"		,"$vol_capa_gb GB", $vol_capa_gb_result, 'vol_capa_gb');
$p = &table_2($p,30 ,"HDD1モデル"	,$dn1);
$p = &table_2($p,30 ,"HDD1ファーム"	,$fv1);
$p = &table_2($p,30 ,"HDD1シリアル"	,$sn1);
$p = &table_2($p,30 ,"HDD1容量"		,"$capa_gb1 GB", $capa_gb1_result, 'capa_gb1');
$p = &table_2($p,30 ,"ボタン/LED"	,$button, $button_result, 'button');
$p = &table_2($p,30 ,"ユーザ情報"	,"整合性確認", $users_result, 'users');
$p = &table_2($p,30 ,"共有フォルダ情報"	,"整合性確認", $shares_result, 'shares');

&table_footer();

print <<EOM;
<form name ="thispage" method="post" action="/log_clear.cgi" onSubmit="return setime(this)">
<input type="submit" name="submitbtn" value="システムログを消去してシャットダウンする">
</form>
</center>
</body>
</html>
EOM
