 /**************************************************\
|                                                    |
|              VideoStream Class                     |
|            ---------------------                   |
|  This class creates and controls a video stream    |
|  object, once started, it is self sustaining and   |
|  has members for changing its resolution, mapping  |
|  the image object and the stream name to the dom   |
|  tree, and controling the feed (start, stop, pause,|
|  rewind, fastforward, seek, ect (most arent done,  |
|  but will be comming soon)).                       |
|                                                    |
 \**************************************************/
	function createMethodReference(object, methodName) {
		return function () {
			object[methodName]();
		};
	};
	/**** Singleton Array of all Streams ****/
	VideoStream.prototype.allStreams = new Array(0);

	 /******************************************\
	|   VideoStream constructor                  |
	|   -----------------------                  |
	|   Parameters:                              |
	|       name:  String name of the video      |
	|    locator:  IP address or Domain Name of  |
	|              the video provider            |
	|       hash:  Identifier hash passed to the |
	|              provider for authentication   |
	|  videoSize:  String name of the resolution |
	|              to use, (e.g. high, low)      |
    |                                            |
     \******************************************/
	function VideoStream(name, locator, hash, frameSize) {

		this.loadVideo(name, locator, hash, frameSize);

		VideoStream.prototype.allStreams[name] = this;
		this.streamID = name;
		//this.streamID = (VideoStream.prototype.allStreams.push(-1)-1);
		//VideoStream.prototype.allStreams[this.streamID] = this;

	}

	 /******************************************\
     \******************************************/
	VideoStream.prototype.loadVideo = function(name, locator, hash, frameSize) {
		this.origName			= name;
		this.streamName			= document.createElement('p').appendChild(document.createTextNode(name));
		this.streamState		= 'stopped';
		this.streamVideo		= document.createElement('img');
		this.streamVideo.died	= false;

		this.serverLocator		= locator;
		this.serverID			= locator.toLowerCase().replace(/^(http:\/\/)?(.*?)\..*/, '$2');
		this.serverHash			= hash;
		this.dontprompt			= false;

		this.frameDelay			= 400;
		this.frameSize			= frameSize;
		this.frameRate			= 2;
		this.frameDelayTimeout	= -1;
		this.frameLoadDelay		= 2000;
		this.frameLoadTimeout	= -1;
		this.frameLagCount		= 0;
		this.frameIndex			= 0;
		this.frameTestTimeout	= -1;
		this.frameLag 			= 0;

		this.streamMode			= false;
		this.startFrameURL		= this.buildURL();
		this.startFrameStatic	= false;
		this.streamVideo.src	= this.startFrameURL;

		this.nurse_privacy		= false;
		this.patient_privacy	= false;

		//var sizes = frameSize.split('x');
		//this.streamVideo.setAttribute('width', sizes[0]);
		//this.streamVideo.setAttribute('height', sizes[1]);
	}


 /************************************\
| **** Video Controller Functions **** |
 \************************************/
	VideoStream.prototype.start = function() {
		if (this.streamState == 'playing') return true;
		this.streamState = 'playing';
		if (this.frameRef >= this.total_frames) this.frameRef = 0;
//		this.streamVideo.onload = createMethodReference(this, "delayFrame");
		this.streamVideo.src = this.buildURL();
		this.frameTestTimeout = setTimeout(createMethodReference(this, 'checkFeed'), this.frameDelay);
	}
	VideoStream.prototype.stop = function() {
		if (this.streamState == 'stopped') return true;
		this.streamState = 'stopped';
		this.frameRef = 0;
		this.frameInc = 1;

		this.resetTimeouts();
		this.streamVideo.src = this.startFrameURL;
		if (!this.startFrameStatic) this.reloadStartFrame();

		return true;
	}	
	VideoStream.prototype.pause = function() {
		if (this.streamState == 'stopped' || this.streamState == 'paused') return true;
		this.streamState = 'paused';
		this.resetTimeouts();
	}

	VideoStream.prototype.reloadStartFrame = function() {
		if (this.streamState != 'stopped') return false;
		this.startFrameURL = this.buildURL();
		this.streamVideo.src = this.startFrameURL;
	}


 /**********************************\
| ******* Mapping Functions ******** |
 \**********************************/

	 /*************************************\
	|   mapUnder:  Maps one object as a     |
	|              child to to another      |
	|   ---------------------------------   |
	|   Parameters:                         |
	|      parent_obj:  object to map to    |
	|      source_obj:  object being mapped |
	|      class_name:  class of source     |
	|                                       |
	 \*************************************/
	VideoStream.prototype.mapUnder = function(parent_obj, source_obj, class_name) {
//		source_obj.setAttribute('class', class_name);
		parent_obj.appendChild(source_obj);
	}
	 /*************************************\
	|   mapBefore:  Maps one object after   |
	|               another object in DOM   |
	|   ---------------------------------   |
	|   Parameters:                         |
	|     sibling_obj:  object to map after |
	|      source_obj:  object being mapped |
	|      class_name:  class of source     |
	|                                       |
	 \*************************************/
	VideoStream.prototype.mapBefore = function(sibling_obj, source_obj, class_name) {
//		source_obj.setAttribute('class', class_name);
		sibling_obj.offsetParent.insertBefore(source_obj, sibling_obj);
	}
	 /*************************************\
	|  mapVideoUnder/  Wrappers to mapUnder |
	| mapVideoBefore:  and mapAfter passing |
	|                  the Video object     |
	|  -----------------------------------  |
	|  Parameters:                          |
	|      parent_id/  ID of parent or      |
	|     sibling_id:  sibling              |
	|     class_name:  class of source      |
	|                                       |
	 \*************************************/
	VideoStream.prototype.mapVideoUnder = function(parent_id, class_name) {
		var our_parent = document.getElementById(parent_id);
		if (undefined == our_parent) return false;
		this.mapUnder(our_parent, this.streamVideo, class_name);
		return true;
	}
	VideoStream.prototype.mapVideoBefore = function(sibling_id, class_name) {
		var our_sib = document.getElementById(sibling_id);
		if (undefined == our_sib) return false;
		this.mapBefore(our_sib, this.streamVideo, class_name);
		return true;
	}

	VideoStream.prototype.replaceVideoUnder = function(parent_id) {
		var our_parent = document.getElementById(parent_id);
		our_parent.replaceChild(this.streamVideo, our_parent.firstChild);
	}
	 /*************************************\
	|   mapNameUnder/ Wrappers to mapUnder  |
	|  mapNameBefore: and mapAfter passing  |
	|                 the streamName object |
	|  ------------------------------------ |
	|  Parameters:                          |
	|      parent_id/  ID of parent or      |
	|     sibling_id:  sibling              |
	|     class_name:  class of source      |
	|                                       |
	 \*************************************/
	VideoStream.prototype.mapNameUnder = function(parent_id, class_name) {
		var our_parent = document.getElementById(parent_id);
		if (undefined == our_parent) return false;
		this.mapUnder(our_parent, this.streamName, class_name);
		return true;
	}
	VideoStream.prototype.mapNameBefore = function(sibling_id, class_name) {
		var our_sib = document.getElementById(sibling_id);
		if (undefined == our_sib) return false;
		this.mapBefore(our_sib, this.streamName, class_name);
		return true;
	}

	VideoStream.prototype.replaceNameUnder = function(parent_id) {
		var our_parent = document.getElementById(parent_id);
		our_parent.replaceChild(this.streamName, our_parent.firstChild);
	}

 /****************************************\
| ****** Video Continuity Functions ****** |
| ** You shouldn't have to modify these ** |
 \****************************************/

	 /*************************************\
	|   delayFrame:  Called after a frame   |
	|                is loaded to create a  |
	|                delay between frames   |
	|   ----------------------------------  |
	|   Parameters:                         |
	|      id: The array slice ID for this  |
	|          Video object.                |
	|                                       |
	 \*************************************/

	VideoStream.prototype.checkFeed = function() {
		if (this.streamState == 'stopped' || this.streamState == 'paused') return;
//		var newindex = readCookie(this.serverID + '_index');

/*		if (newindex == this.frameIndex) {
			this.frameLag++;
			if (this.frameLag > 2) {
//				alert(this.serverID + ' has stopped, restarting...');
				this.streamVideo.src = this.buildURL();
			}
		} else {
			this.frameIndex = newindex;
		}
*/
		if (this.streamVideo.complete) {
			this.streamVideo.src = this.buildURL();
			this.frameTestTimeout = setTimeout(createMethodReference(this, 'checkFeed'), this.frameDelay);
		} else {
			if (++this.frameLag > 5) {
				this.streamVideo.src = this.buildURL();
				this.frameTestTimeout = setTimeout(createMethodReference(this, 'checkFeed'), this.frameDelay);
				this.frameLag = 0;
			} else {
				this.frameTestTimeout = setTimeout(createMethodReference(this, 'checkFeed'), 100);
			}
		}
	}

	function readCookie(name) {
		var nameEQ = name + "=";
		var ca = document.cookie.split(';');
		for (var i=0; i < ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0)==' ') c = c.substring(1,c.length);
			if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
		}
		return null;
	}


	VideoStream.prototype.delayFrame = function() {
		if (undefined == this) return true;
		if (this.streamVideo.died) return;

		this.resetTimeouts();


		if (this.streamMode) {
			if (!this.streamVideo.complete) return;
			if (this.streamVideo.naturalWidth == 0) {
				setTimeout(createMethodReference(this, 'delayFrame'), 2000);
				return;
			}
			if (this.streamState == 'playing') {
/*				var parent = this.streamVideo.parentNode;

				var newimg = document.createElement('img');
				newimg.setAttribute('width', this.streamVideo.getAttribute('width'));
				newimg.setAttribute('height', this.streamVideo.getAttribute('height'));
				newimg.targetObj = this;
				newimg.died = false;
				newimg.i = this.streamVideo.i+1;
				newimg.onload = function() {
					this.targetObj.streamVideo = this;
					this.onload = createMethodReference(this.targetObj, 'delayFrame');
				}
				this.streamVideo.died = true;
				newimg.src = this.buildURL();
*/
				this.streamVideo.src = this.buildURL();
				return;
			}
			return;
		}

		switch (this.streamState) {
			case 'stopped':
//				alert('stopped');
				break;
			case 'paused':
				break;
			case 'stopping':
/*				this.streamState = 'stopped';
				alert('stopping');
				if (this.streamMode) {
					this.streamMode = false;
					this.streamVideo.src = this.buildURL();
					this.streamMode = true;
				}
*/
				break;
			case 'playing':
				if (this.frameLagCount > 0) this.frameLagCount--;
				clearTimeout(this.frameDelayTimeout);
				this.frameDelayTimeout = setTimeout(
											function () { this['reloadFrame']; },
											this.frameDelay
										);
				break;
			default:
				alert('state unknown: '+this.streamState);
				break;
		}
	}

	VideoStream.prototype.resetTimeouts = function() {
		clearTimeout(this.frameDelayTimeout);
		clearTimeout(this.frameLoadTimeout);
	}

	 /**************************************\
	|  reloadFrame: Called after delayFrame, |
	|               buildURL returns new src |
	|  ------------------------------------  |
	|  Parameters:                           |
	|     id:  The array slice ID for this   |
	|          Video object.                 |
	|                                        |
	 \**************************************/
	VideoStream.prototype.reloadFrame = function() {

		this.streamVideo.src = this.buildURL();
		this.resetTimeouts();

		if (this.streamState == 'playing') {
			this.frameLoadTimeout = setTimeout(
										createMethodReference(this, "timeoutFrame"),
										this.frameLoadDelay
									);

		}
	}


	VideoStream.prototype.timeoutFrame = function() {
		if (this.streamState != 'playing') return true;
//		alert(this.streamID + ' timed out');
		this.resetTimeouts();
		this.frameLagCount++;
//		if (this.frameLagCount > 5 && this.frameSize != '160x120') {
//			this.frameSize = '160x120';
//		}
		this.reloadFrame();
	}
	 /*************************************\
	|   buildURL:  Called to build the URL  |
	|              of the video provider.   |
	|   ----------------------------------  |
	|   Parameters:  None                   |
	|                                       |
	 \*************************************/
	VideoStream.prototype.buildURL = function() {

		if (this.nurse_privacy || this.patient_privacy)
			return '/hrc.new/img/ezfw_privacy.gif';

		var path = this.serverLocator + '?';
		if (!this.streamMode || this.streamState != 'playing') path += '&single=true';
		path += '&size=' + this.frameSize;
		path += '&rate=' + this.frameRate;
		path += '&t=' + (new Date()).getTime();

		return path;
	}
	/*******************************************/


