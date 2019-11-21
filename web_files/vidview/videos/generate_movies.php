<?php


//Make the thumbnails

$movie_name = "1219087521-1219087582";
$thumbnail_offset = 45;

system("ffmpeg -i {$movie_name}.mpg -ss $thumbnail_offset -t 0.000001 {$movie_name}_%d.jpg");
system("mv {$movie_name}_1.jpg {$movie_name}.jpg");
system("rm {$movie_name}_*.jpg");


//Make the flash movie

$frame_rate = 10;
$bit_rate = 16384;

system("ffmpeg -i {$movie_name}.mpg -an -r $frame_rate -b $bit_rate {$movie_name}.flv");


