#!/usr/bin/perl -w

my $ver = 4;
my $width = 176;
my $height = 144;
my $foot = "\x56\x9a\x72\x4d\xdb\xfb\x03\x00\x30\x80\x80\x10";

$width = int($ARGV[0]) if @ARGV;
$height = int($ARGV[1]) if (@ARGV > 1);


print (pack("LLL", $ver, $width, $height) . $foot);
#print "$width x $height\n";

