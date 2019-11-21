var dragging = false;
var start_pos = false; //{ x:false, y:false };
var selection = false; //
var frame = false;

var zones = new Array();


function selection_start(event) {
	selection = document.createElement('div');
	selection.className = 'selectionbox';
	start_pos = { x:event.clientX - 2, y:event.clientY - 2 };

	selection.style['top'] = event.clientY + 'px';
	selection.style['left'] = event.clientX + 'px';
	selection.style['width'] = '4px';
	selection.style['height'] = '4px';
	dragging = true;

	document.body.appendChild(selection);

//	document.body.onmousemove='selection_size_update(event)';
//	document.body.onmouseup='selection_stop(event)';
}

function selection_size_update(event) {
	if (!dragging) return;
	if (event.clientX < start_pos.x) {
		var x = (start_pos.x - event.clientX);
		selection.style['width'] = x  + 'px';
		selection.style['left'] = event.clientX + 'px';
	} else {
		selection.style['width'] = (event.clientX - start_pos.x) + 'px';
	}

	if (event.clientY < start_pos.y) {
		var y = (start_pos.y - event.clientY);
		selection.style['height'] = y + 'px';
		selection.style['top'] = event.clientY + 'px';
	} else {
		selection.style['height'] = (event.clientY - start_pos.y) + 'px';
	}

}

function selection_stop() {
	if (!dragging) return;
	dragging = false;

//	document.body.onmousemove='';
//	document.body.onmouseup='';

	if (!frame) frame = document.getElementById('frame');

	var vertex = new Array(2);
	var i;
	var divbox = selection;

	for (i = 0; i < 2; i++) {
		vertex[i] = {x:0, y:0};

		vertex[i].x = parseInt(selection.style['left']);
		if (i) vertex[i].x += parseInt(selection.style['width']);
		vertex[i].x -= parseInt(frame.offsetLeft);

		vertex[i].y = parseInt(selection.style['top']);
		if (i) vertex[i].y += parseInt(selection.style['height']);
		vertex[i].y -= parseInt(frame.offsetTop);
	}

	zones.push({vertices:vertex, box:divbox});

	selection = false;
	start_pos = false;

	alert("New Zone Pushed:\n" + vertex[0].x + ':' + vertex[0].y + ' -- ' + vertex[1].x + ':' + vertex[1].y);

}
