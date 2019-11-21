var video_obj = false;
var load_bar = false;
var video_opened = false;
var status_script = false;
var update_seq = 1;
var updateTimeout = false;
var my_slider = false;
function wait_update() {
	if (updateTimeout) clearTimeout(updateTimeout);
	updateTimeout = setTimeout('update_status()', 200);
}

function update_status() {
	update_load_bar(-1); 
	if (!video_obj) return;
	if (video_opened && !video_obj.load_complete) {
		if (!status_script) status_script = document.getElementById('status_script');
		var new_script = document.createElement('script');
		new_script.setAttribute('type', 'text/javascript');
		new_script.setAttribute('src', 'demux_status.php?action=play&ENTRY='+entry_string+'&rate='+video_obj.frameRate+'&ts='+ts_string+'&seq='+update_seq++);
		//if (status_script.firstChild) status_script.replaceChild(new_script, status_script.firstChild);
		//else status_script.appendChild(new_script);
		document.body.appendChild(new_script);
	}
}

function update_load_bar() {
	if (!video_obj) return;
	if (video_obj.loaded_frames > video_obj.total_frames) {
		video_obj.total_frames = video_obj.loaded_frames;
	}
	var percent = video_obj.loaded_frames / video_obj.total_frames * 100;
	var new_width = Math.ceil(368 * (percent / 100));
	if (video_opened && my_slider) my_slider.updateControlSize(new_width, video_obj.loaded_frames);
}
function create_video_object(title) {
	video_obj = new VideoStream(
						title,
						'img/wrld.gif',
						'',
						'352x288'
					);
		video_obj.frameRate = -1;
		video_obj.frameInc = 1;
		video_obj.frameRef = -1;
		video_obj.total_frames = -1;
		video_obj.load_complete = false;
		video_obj.buildURL = function(no_inc) {
			if (!no_inc) {
				if (this.frameRef < 0) this.frameRef = 1;
				else this.frameRef += this.frameInc;
			}
			if (this.frameRef > this.total_frames) {
				this.frameRef = this.total_frames;
				this.pause();
				button_set(document.getElementById('control_pause'), 'pause');
				refresh_control_display(true);
				return this.streamVideo.src;
			} else if (this.frameRef > this.loaded_frames) {
				this.frameRef = this.loaded_frames;
				this.frameInc = 1;
				refresh_control_display(true);
				return this.streamVideo.src;
			}

			refresh_control_display();
			return this.serverLocator + '/video_' + this.frameRef + '.jpg';
		}
		video_obj.mapVideoUnder('videoContainer');
		if (video_obj.total_frames > 0) {
			check_first_frame();
			update_load_bar();
		}
}
function check_first_frame() {
	if (!video_obj) return;
	if (video_obj.loaded_frames > 0) video_obj.streamVideo.src = video_obj.buildURL();
	else setTimeout('check_first_frame()', 200);
}


// Display Control Functions
var control_display = false;
var prev_active_button = false;
var prev_active_button_url = 'img/control/inactive/stop.png';
var video_position;
//var slider_obj = false;
//var start_sec = start_time - (new Date()).getTimezoneOffset() * 60 * 1000;
function button_ref (obj, url) {
	return function() {
		obj.src = url;
	};
};
function button_set (obj, ctrl_name) {
	if (!video_opened) return;
	if (!obj) return;
	var exec_common = false;
	if (!prev_active_button) prev_active_button = document.getElementById('control_stop');
	switch (ctrl_name) {
		case('back'):
			video_obj.frameInc = video_obj.frameInc / 2;
			var last_url = obj.src;
			obj.src = 'img/control/active/back.png';
			setTimeout(button_ref(obj, last_url), 200);
			break;
		case('forward'):
			video_obj.frameInc *= 2;
			var last_url = obj.src;
			obj.src = 'img/control/active/forward.png';
			setTimeout(button_ref(obj, last_url), 200);
			break;
		case('play'):
			if (video_obj.streamState == 'playing' && video_obj.frameInc > 1) video_obj.frameInc = 1;
			video_obj.start();
			if (prev_active_button && obj == prev_active_button) break;
			exec_common = true;
			break;
		case('stop'):
			video_obj.stop();
			video_obj.frameRef = 1;
			if (prev_active_button && obj == prev_active_button) break;
			exec_common = true;
			break;
		case('pause'):
			video_obj.pause();
			if (prev_active_button && obj == prev_active_button) break;
			exec_common = true;
			break;
	}
	if (exec_common) {
		var last_url = obj.src;
		obj.src = 'img/control/active/' + ctrl_name + '.png';
			prev_active_button.src = prev_active_button_url;
			prev_active_button_url = last_url;
			prev_active_button = obj;
	}
	refresh_control_display();
}
function seek_to_cursor(e) {
	if (e.clientX) mouseX = e.clientX
	else mouseX = e;
	if (!video_opened) return false;
	var rx = mouseX - load_bar.x;
	var percent = rx / 368;
	var frame = Math.floor(video_obj.total_frames * percent - 3);
	if (frame > video_obj.loaded_frames) frame = video_obj.loaded_frames;
	video_obj.frameRef = frame;
	refresh_control_display();
	video_obj.streamVideo.src = video_obj.buildURL();
}
function refresh_control_display(no_slide_update) {
	if (video_obj.frameInc > 8) video_obj.frameInc = 8;
	else if (video_obj.frameInc < 1) video_obj.frameInc = 1;

	update_load_bar();
	var sec_elapsed = Math.floor(video_obj.frameRef / video_obj.frameRate);
	var tmpdate = new Date( start_time + (sec_elapsed * 1000));

	video_position = '';
	if (tmpdate.getUTCHours() < 10) video_position = '0';
	video_position += tmpdate.getUTCHours() + ':';
	if (tmpdate.getUTCMinutes() < 10) video_position += '0';
	video_position += tmpdate.getUTCMinutes() +':';
	if (tmpdate.getUTCSeconds() < 10) video_position += '0';
	video_position += tmpdate.getUTCSeconds();

	document.getElementById('stamp').value = video_position + '  (frame: ' + video_obj.frameRef + ' / ' + video_obj.total_frames + ')';
	var txt = document.createTextNode(
		video_obj.streamState +
		' (' + video_obj.frameInc + 'x)  '
	);
	if (!control_display) control_display = document.getElementById('control_display');
	if (control_display)
		if (control_display.firstChild)
			control_display.replaceChild(txt, control_display.firstChild);
		else
			control_display.appendChild(txt);

	if (!no_slide_update && my_slider) my_slider.f_setValue(video_obj.frameRef);

}
