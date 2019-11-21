var box = {};
var frame;
var edges;
var fmark;
var emark;
var moving = 1;
var inside = 1;
function get_pos (e) {
    var pos = {x:0,y:0};
    pos.w = e.clientWidth;
    pos.h = e.clientHeight;
    do {
        pos.x += e.offsetLeft;
        pos.y += e.offsetTop;
        e = e.offsetParent;
    } while (e instanceof Object && e.clientWidth != undefined);
    return pos;
}
function mouseCoords (ev) {
    return {
        x:ev.clientX + document.body.scrollLeft - document.body.clientLeft,
        y:ev.clientY + document.body.scrollTop - document.body.clientTop,
    };
}
function mousemove (ev) {
    if (!moving || !inside) return true;
    if (!fmark.style.visibility) {
        fmark.style.visibility = 'visible';
        emark.style.visibility = 'visible';
    }
    ev = ev || window.event;
    var pos = mouseCoords(ev);
    var fpos =get_pos(frame);
    var epos = get_pos(edges);

    var ptr = {x:0,y:0};
//    if (pos.y > (fpos.h + fpos.y - (fmark.clientHeight>>1 - 1))) return false;
    if (pos.x < epos.x) {
//                if (this == frame) {
        ptr.x = (pos.x - fpos.x) - (fmark.clientWidth>>1);
        ptr.y = (pos.y - fpos.y) - (fmark.clientHeight>>1);
    } else { //if (this == edges) {
        ptr.x = (pos.x - epos.x) - (emark.clientWidth>>1);
        ptr.y = (pos.y - epos.y) - (emark.clientHeight>>1);
    }
/*                
    } else {
        return false;
    }
*/                
    set_rel_pos(emark, epos, ptr);
    set_rel_pos(fmark, fpos, ptr);

    return true;
}

function set_inside (v) {
    inside = !!v;
    if (inside) {
        fmark.style.visibility = 'visible';
        emark.style.visibility = 'visible';
        return true;
    }
    if (moving) {
        if (fmark) {
            fmark.style.visibility = '';
            fmark.style.display = '';
        }
        if (emark) {
            emark.style.visibility = '';
            emark.style.display = '';
        }

    }
    return true;
}
function set_rel_pos (elem, base, off) {
    elem.style.left = (base.x + off.x) + 'px';
    elem.style.top = (base.y + off.y) + 'px';
    return;
}

function toggle_moving (ev) {
    ev = ev || window.event;
    if (!moving) {
        set_moving(true);
        mousemove(ev);
    } else
        set_moving(false);
    ev.cancelBubble = true;
    return false;
}
function set_moving (m) {
    if (!m) {
        frame.onmousemove = null;
        edges.onmousemove = null;
        fmark.onmousemove = null;
        emark.onmousemove = null;
        fmark.style.cursor = '';
        emark.style.cursor = '';
        moving = false;
        return true;
    }

    frame.onmousemove = mousemove;
    edges.onmousemove = mousemove;
    fmark.onmousemove = mousemove;
    emark.onmousemove = mousemove;
    fmark.style.cursor = 'none';
    emark.style.cursor = 'none';
    moving = true;
    return true;
}

function set_box(n, elem, pos) {
    box[n] = document.createElement('div');
    box[n].className = 'boxes';
//    box[n].style.position = 'absolute';
    box[n].style.left = pos.x + 'px';
    box[n].style.top = pos.y + 'px';
    box[n].style.width = pos.w + 'px';
    box[n].style.height = elem.clientHeight + 'px'; //pos.h + 'px';
//    box[n].style.zIndex = 10;
    box[n]._elem = elem;

    box[n].onmousedown = function (ev) { return (this._elem.onmousedown instanceof Function)?this._elem.onmousedown(ev || window.event):false; }
    box[n].onmouseup   = function (ev) { return (this._elem.onmouseup instanceof Function)?this._elem.onmouseup(ev || window.event):false; }
    box[n].onmouseover = function (ev) {
        set_inside(true); 
        return (this._elem.onmouseover instanceof Function)?this._elem.onmouseover(ev || window.event):false;
    }
    box[n].onmouseout  = function (ev) { 
        set_inside(false);
        return (this._elem.onmouseout instanceof Function)?this._elem.onmouseout(ev || window.event):false; 
    }

//    elem.parentNode.appendChild(box[n]);
    document.body.appendChild(box[n]);
    return box[n];
}

function init () {
    document.getElementById('samplebox').focus();
    frame = document.getElementById('frame');
    edges = document.getElementById('edges');
    fmark = document.getElementById('frame_mark');
    emark = document.getElementById('edge_mark');

    if (!frame || !edges) return;

    var fpos = get_pos(frame);
    var epos = get_pos(edges);

    set_box('frame', frame, fpos);
    set_box('edges', edges, epos);

    frame.onmousedown = edges.onmousedown = 
    fmark.onmousedown = emark.onmousedown = toggle_moving;

    window.onmousemove = mousemove;
/*    
        set_moving(true);
        return mousemove(ev);
    }
*/
    set_moving(true);
    set_rel_pos(fmark, fpos, {x:((fpos.w>>1) - (fmark.clientWidth>>1)), y:((fpos.h>>1) - (fmark.clientHeight>>1))});
    set_rel_pos(emark, epos, {x:((epos.w>>1) - (emark.clientWidth>>1)), y:((epos.h>>1) - (emark.clientHeight>>1))});
    
    fmark.style.display = 'none';
    emark.style.display = 'none';

    return;
}

