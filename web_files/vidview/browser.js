var selectedEntries = {'length':0};
var focusedEntry = null;

function dayClick(day) {
    dateObj.setDate(day);
    ts = dateObj.getTime()/1000;
    document.location = 'browser.php?ts='+ts;
}

function previousMonth () {
    dateObj.setDate(1);
    dateObj.setMonth(dateObj.getMonth()-1);
    ts = dateObj.getTime()/1000;
    document.location = 'browser.php?ts='+ts;
}

function nextMonth () {
    dateObj.setDate(1);
    dateObj.setMonth(dateObj.getMonth()+1);
    ts = dateObj.getTime()/1000;
    document.location = 'browser.php?ts='+ts;
}

function set_date (ts) {
    var y = dateObj.getYear();
    var m = dateObj.getMonth();
    var d = dateObj.getDate();

    dateObj.setTime(ts*1000);
    ts = dateObj.getTime()/1000;

    if (y != dateObj.getYear() || m != dateObj.getMonth() || d != dateObj.getDate())
        document.location = 'browser.php?ts='+ts;
}

function set_focused (id) {
    var sel = document.getElementById(id);
    if (undefined == sel || sel == null) return;
    focusEntry(sel);
}

function focusEntry (obj) {
    unfocusEntry();
    focusedEntry = obj;
    addClass(obj, 'focusedEntry');
    scrollTo(obj);
}

function scrollTo (obj) {
    if (undefined == obj || obj == null) return;
    var pos = obj.offsetTop;
    var scr = obj.parentNode.parentNode.parentNode;

    if (pos >= scr.scrollTop && pos <= (scr.scrollTop + scr.clientHeight)) return;
    pos -= (obj.clientHeight * 4);
    if (pos < 0) pos = 0;
    obj.parentNode.parentNode.parentNode.scrollTop = pos;
    return;
}

function unfocusEntry () {
    if (focusedEntry == null) return;
    removeClass(focusedEntry, 'focusedEntry');
    focusedEntry = null;
    return;
}

function selectChecked () {
    var tbl = document.getElementById('entries');
    if (undefined == tbl || tbl == null) return;

    selectedEntries = {'length':0};

    for (var i = 0; i < tbl.rows.length; i++) {
        if (tbl.rows[i].childNodes[1].firstChild.checked) {
            addClass(tbl.rows[i], 'selectedEntry');
            selectedEntries[tbl.rows[i].getAttribute('id')] = tbl.rows[i];
            selectedEntries.length++;
        }
    }
    if (selectedEntries.length) enableDownloadButton();
    else disableDownloadButton();

}

function selectEntry (id) {
    if (undefined == id || undefined != selectedEntries[id]) return;
    var sel = document.getElementById(id);
    if (undefined == sel || sel == null) return;
    selectedEntries[id] = sel;
    selectedEntries.length++;
    if (selectedEntries.length == 1)
        enableDownloadButton();

    addClass(sel, 'selectedEntry');
    return;
}

function unselectEntry (id) {
    if (undefined == id || undefined == selectedEntries[id]) return;
    if (id == 'all') {
        for (var i in selectedEntries)
            unselectEntry(i);
        return;
    }

    var sel = selectedEntries[id];
    delete(selectedEntries[id]);

    selectedEntries.length--;
    if (!selectedEntries.length)
        disableDownloadButton();

    removeClass(sel, 'selectedEntry');
    return;
}
function disableDownloadButton () {
    var tmp = document.getElementById('dlButton');
    tmp.src = 'images/dl.gif';
    removeClass(tmp, 'enabled');
}


function enableDownloadButton () {
    var tmp = document.getElementById('dlButton');
    tmp.src = 'images/dl_enabled.gif';
    addClass(tmp, 'enabled');
}


function downloadSelected () {
    if (!selectedEntries.length) {
        alert('Please select videos to download by clicking the check boxes before clicking this button.');
        return;
    }

    var q = '&type=list';
    var c = 0;

    for (var i in selectedEntries) {
        if (i == 'length') continue;
        q += '&v['+c+']='+i;
        c++;
    }
    document.location = 'get_movie.php?' + q;
    return;
}

function removeClass (obj, clsname) {
    if (undefined == obj || obj == null) return;
    var tmp = obj.className.split(' ');
    var newcls = '';
    for (var i = 0; i < tmp.length; i++)
        if (tmp[i] != clsname) newcls += ' ' + tmp[i];
    
    obj.className = newcls;
    return;
}

function addClass (obj, clsname) {
    if (undefined == obj || obj == null) return;
    obj.className += ' ' + clsname;
    return;
}

function entryClick (obj) {
    var row = obj.parentNode;
    // obj is the td cell of the start/stop/duration cells
    // obj.parentNode.getAttribute('entryId')
    //  alert('entry click for entry #'+row.getAttribute('entryId'));
    var id = row.getAttribute('id');
    focusEntry(row);
    top.frames.player.document.location = 'player.php?id=' + id;
    return true;
}


function dlClick (obj) {
    var row = obj.parentNode.parentNode;
    var id = row.getAttribute('id');
    // obj is the checkbox input element of the entry
    // obj.parentNode.parentNode.getAttribute('entryId')
    // obj.
//  obj.value = (obj.value=='false')?'true':'false';
    if (undefined != selectedEntries[id])
        unselectEntry(id);
    else
        selectEntry(id);

//  obj.parentNode.parentNode.setAttribute('id', (obj.checked?'selectedEntry':''));
//  alert('download click for entry #'+row.getAttribute('entryId'));
    return true;
}
function eventClick (obj) {
    var row = obj.parentNode.parentNode;
    // obj is the img tag, which 
    // obj.parentNode.parentNode.getAttribute('entryId')
    // obj.getAttribute('eventName')
//  alert('"'+obj.getAttribute('eventName')+'" event click for entry #'+row.getAttribute('entryId'));
    return true;
}

