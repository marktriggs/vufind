var GetStatusList = new Array();
var GetSaveStatusList = new Array();
var GetExtIdsList = new Array();
var GetHTIdsList = new Array();

function getStatuses(id)
{
    GetStatusList[GetStatusList.length] = id;
}

function doGetStatuses(strings)
{
    // Do nothing if no statuses were requested:
    if (GetStatusList.length < 1) {
        return;
    }

    var now = new Date();
    var ts = Date.UTC(now.getFullYear(),now.getMonth(),now.getDay(),now.getHours(),now.getMinutes(),now.getSeconds(),now.getMilliseconds());

    var url = path + "/Search/AJAX?method=GetItemStatuses";
    for (var i=0; i<GetStatusList.length; i++) {
       url += "&id[]=" + encodeURIComponent(GetStatusList[i]);
    }
    url += "&time="+ts;

    var callback =
    {
        success: function(http) {
            var response = http.responseXML.documentElement;
            var items = response.getElementsByTagName('item');
            var elemId;
            var statusDiv;
            var status;
            var reserves;

            for (i=0; i<items.length; i++) {
                elemId = items[i].getAttribute('id');
                statusDiv = getElem('status' + elemId);

                var reserveTags = items[i].getElementsByTagName('reserve');
                if (reserveTags && reserveTags.item(0).firstChild) {
                    reserves = reserveTags.item(0).firstChild.data;
                } else {
                    reserves = 'N';
                }

                if (statusDiv) {
                    if (reserves == 'Y') {
                        statusDiv.innerHTML = '';
                    } else if (items[i].getElementsByTagName('availability')) {
                        if (items[i].getElementsByTagName('availability').item(0).firstChild) {
                            status = items[i].getElementsByTagName('availability').item(0).firstChild.data;
                            // write out response
                            if (status == "true") {
                                statusDiv.innerHTML = strings.available;
                            } else {
                                statusDiv.innerHTML = strings.unavailable;
                            }
                        } else {
                            statusDiv.innerHTML = strings.unknown;
                        }
                    } else {
                        statusDiv.innerHTML = strings.unknown;
                    }
                }

                if (items[i].getElementsByTagName('location')) {
                    var callnumber
                    var location = items[i].getElementsByTagName('location').item(0).firstChild.data;

                    var locationDiv = getElem('location' + elemId);
                    if (locationDiv) {
                        if (reserves == 'Y') {
                            locationDiv.innerHTML = strings.reserve;
                        } else {
                            locationDiv.innerHTML = location;
                        }
                    }

                    var callnumberDiv = getElem('callnumber' + elemId);
                    if (callnumberDiv) {
                        if (items[i].getElementsByTagName('callnumber').item(0).firstChild) {
                            callnumber = items[i].getElementsByTagName('callnumber').item(0).firstChild.data
                            callnumberDiv.innerHTML = callnumber;
                        } else {
                            callnumberDiv.innerHTML = '';
                        }
                    }
                }
            }
        }
    };
    YAHOO.util.Connect.asyncRequest('GET', url, callback, null);
}

function saveRecord(id, formElem, strings)
{
    successCallback = function() {
        // Redraw the statuses to reflect the change:
        doGetSaveStatuses();
    };
    performSaveRecord(id, formElem, strings, 'VuFind', successCallback);
}

function getSaveStatuses(id)
{
    GetSaveStatusList[GetSaveStatusList.length] = id;
}

function doGetSaveStatuses()
{
    if (GetSaveStatusList.length < 1) {
        return;
    }

    var now = new Date();
    var ts = Date.UTC(
        now.getFullYear(), now.getMonth(), now.getDay(), now.getHours(),
        now.getMinutes(), now.getSeconds(), now.getMilliseconds()
    );

    var url = path + "/AJAX/JSON?method=getSaveStatuses";
    for (var i=0; i<GetSaveStatusList.length; i++) {
        url += "&id[]" + "=" + encodeURIComponent(GetSaveStatusList[i]);
    }
    url += "&time="+ts;

    var callback =
    {
        success: function(http) {
            var response = eval('(' + http.responseText + ')');
            if (response && response.status == 'OK') {
                // Collect lists together by ID:
                var lists = [];
                for (var i = 0; i < response.data.length; i++) {
                    var current = response.data[i];
                    if (lists[current.record_id] == null) {
                        lists[current.record_id] = '';
                    }
                    lists[current.record_id] += '<li><a href="' + path +
                        '/MyResearch/MyList/' + current.list_id + '">' +
                        jsEntityEncode(current.list_title) + '</a></li>';
                }

                // Render all the grouped lists to the page:
                for (var i in lists) {
                    YAHOO.util.Dom.addClass(
                        document.getElementById('saveLink' + i), 'savedFavorite'
                    );
                    getElem('lists' + i).innerHTML = lists[i];
                }
            }
        }
    };
    YAHOO.util.Connect.asyncRequest('GET', url, callback, null);
}

function getExtIds(extId)
{
    GetExtIdsList[GetExtIdsList.length] = extId;
}

function doGetExtIds()
{
    var extIdsParams = "";
    for (var i=0; i<GetExtIdsList.length; i++) {
        if (GetExtIdsList[i].length > 0) {
            extIdsParams += encodeURIComponent(GetExtIdsList[i]) + ",";
        }
    }
    return extIdsParams;
}

function getHTIds(htConcat)
{
    GetHTIdsList[GetHTIdsList.length] = htConcat;
}

function doGetHTIds()
{
    var extHTParams = "";
    for (var i=0; i<GetHTIdsList.length - 1; i++) {
        extHTParams += encodeURIComponent(GetHTIdsList[i]) + "|";
    }
    extHTParams += encodeURIComponent(GetHTIdsList[GetHTIdsList.length - 1]);
    var retval = extHTParams;
    return retval;
}
