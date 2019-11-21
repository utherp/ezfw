
function enableTab () {
    if (undefined == this.tabBody && !findTabBody.apply(this)) 
        return false;

    var enabled = this.parentNode.parentNode.enabledTab;
    if (enabled && enabled != this) 
        disableTab.apply(enabled);
    
    this.className += ' selected';
    this.tabBody.className += ' selected';

    this.parentNode.parentNode.enabledTab = this;
    return true;
}

function findTabBody () {
    var bodyName = this.getAttribute('tab:body');
    this.tabBody = document.getElementById(bodyName);
    if (undefined == this.tabBody)
        return false;
    return true;
}

function rmcls (obj, cls) {
    var l = obj.className.split(' ');
    var str = '';
    for (var i = 0; i < l.length; i++)
        if (l[i] != cls)
            str += l[i] + ' ';
    obj.className = str;
    return;
}

function disableTab () {
    if (undefined == this.tabBody && !findTabBody.apply(this)) 
        return false;
    
    rmcls(this.tabBody, 'selected');
    rmcls(this, 'selected');
    if (this == this.parentNode.parentNode.enabledTab)
        delete this.parentNode.parentNode.enabledTab;
    return true;
}

