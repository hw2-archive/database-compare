function setSelectOptions(aform, aselect, docheck) {
    var selectObject = document.forms[aform].elements[aselect];
    var selectCount  = selectObject.length;

    for (var i = 0; i < selectCount; i++) {
        selectObject.options[i].selected = docheck;
    }

    return true;
}