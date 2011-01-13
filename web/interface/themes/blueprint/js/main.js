/**
 * This is called when the normal page is loaded.
 */
$(document).ready(function(){
    // disable caching for all AJAX requests
    $.ajaxSetup({cache: false});

    // set global options for the jQuery validation plugin
    $.validator.setDefaults({
        errorClass: 'invalid'
    });

    // register the record comment form to be submitted via AJAX
    // this form is not in a lightbox, but we still want it submitted via AJAX
    registerAjaxCommentRecord();

    // attach jquery validation to forms
    $('form[name="saveRecord"]').validate();
    $('form[name="smsRecord"]').validate();
    $('form[name="emailRecord"]').validate();
    $('form[name="tagRecord"]').validate();
    $('form[name="commentRecord"]').validate();
    $('form[name="emailSearch"]').validate();
    $('form[name="accountForm"]').validate();
    $('form[name="loginForm"]').validate();

    // put focus on the "mainFocus" element
    $('.mainFocus').each(function(){ $(this).focus(); } );

    // support "jump menu" dropdown boxes
    $('select.jumpMenu').change(function(){
        $(this).parent('form').submit();
    });

    // bind click action to export record menu
    $('a.exportMenu').click(function(){
        toggleMenu('exportMenu');
        return false;
    });

    // attach click event to the "keep filters" checkbox
    $('#searchFormKeepFilters').change(function() { filterAll(this); });

    // attach click event to the search help links
    $('a.searchHelp').click(function(){
        window.open(path + '/Help/Home?topic=search', 'Help', 'width=625, height=510');
        return false;
    });

    // attach click event to the advanced search help links
    $('a.advsearchHelp').click(function(){
        window.open(path + '/Help/Home?topic=advsearch', 'Help', 'width=625, height=510');
        return false;
    });

    // bind click action on toolbar links
    $('a.saveRecord').click(function() {
        var id = this.id.substr('saveRecord'.length);
        var $dialog = getLightbox('Record', 'Save', id, null, this.title, 'Record', 'Save', id);
        return false;
    });
    $('a.citeRecord').click(function() {
        var id = this.id.substr('citeRecord'.length);
        var $dialog = getLightbox('Record', 'Cite', id, null, this.title);
        return false;
    });
    $('a.smsRecord').click(function() {
        var id = this.id.substr('smsRecord'.length);
        var module = 'Record';
        if ($(this).hasClass('smsSummon')) {
            module = 'Summon';
        } else if ($(this).hasClass('smsWorldCat')) {
            module = 'WorldCat';
        }
        var $dialog = getLightbox(module, 'SMS', id, null, this.title);
        return false;
    });
    $('a.mailRecord').click(function() {
        var id = this.id.substr('mailRecord'.length);
        var module = 'Record';
        if ($(this).hasClass('mailSummon')) {
            module = 'Summon';
        } else if ($(this).hasClass('mailWorldCat')) {
            module = 'WorldCat';
        }
        var $dialog = getLightbox(module, 'Email', id, null, this.title);
        return false;
    });
    $('a.tagRecord').click(function() {
        var id = this.id.substr('tagRecord'.length);
        var $dialog = getLightbox('Record', 'AddTag', id, null, this.title, 'Record', 'AddTag', id);
        return false;
    });
    $('a.deleteRecordComment').click(function() {
        var commentId = this.id.substr('recordComment'.length);
        var recordId = this.href.match(/\/Record\/([^\/]+)\//)[1];
        deleteRecordComment(recordId, commentId);
        return false;
    });
    $('a.mailSearch').click(function() {
        var id = this.id.substr('mailSearch'.length);
        var $dialog = getLightbox('Search', 'Email', id, null, this.title);
        return false;
    });

    // assign action to the "select all checkboxes" class
    $('input[type="checkbox"].selectAllCheckboxes').change(function(){
        $(this.form).find('input[type="checkbox"]').attr('checked', $(this).attr('checked'));
    });

    // assign action to the openUrlWindow link class
    $('a.openUrlWindow').click(function(){
        var params = extractParams($(this).attr('class'));
        var settings = params.window_settings;
        window.open($(this).attr('href'), 'openurl', settings);
        return false;
    });

    // assign action to the openUrlEmbed link class
    $('a.openUrlEmbed').click(function(){
        var params = extractParams($(this).attr('class'));
        var openUrl = $(this).children('span.openUrl:first').attr('title');
        $(this).hide();
        loadResolverLinks($('#openUrlEmbed'+params.openurl_id).show(), openUrl);
        return false;
    });
});

/**
 * This is called by the lightbox when it
 * finished loading the dialog content from the server
 * to register the form in the dialog for ajax submission.
 */
function lightboxDocumentReady() {
    registerAjaxLogin();
    registerAjaxSaveRecord();
    registerAjaxListEdit();
    registerAjaxEmailRecord();
    registerAjaxSMSRecord();
    registerAjaxTagRecord();
    registerAjaxEmailSearch();
    $('.mainFocus').focus();
}

function registerAjaxLogin() {
    $('#modalDialog > form[name="loginForm"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var form = this;
        $.ajax({
            url: path + '/AJAX/JSON?method=getSalt',
            dataType: 'json',
            success: function(response) {
                if (response.status == 'OK') {
                    var salt = response.data;

                    // get the user entered username/password
                    var password = form.password.value;
                    var username = form.username.value;

                    // encrypt the password with the salt
                    password = rc4Encrypt(salt, password);

                    // hex encode the encrypted password
                    password = hexEncode(password);

                    // login via ajax
                    $.ajax({
                        url: path + '/AJAX/JSON?method=login',
                        dataType: 'json',
                        data: {username:username, password:password},
                        success: function(response) {
                            if (response.status == 'OK') {
                                // Hide "log in" options and show "log out" options:
                                $('#loginOptions').hide();
                                $('#logoutOptions').show();

                                // Update user save statuses if the current context calls for it:
                                if (typeof(checkSaveStatuses) == 'function') {
                                    checkSaveStatuses();
                                }

                                // refresh the comment list so the "Delete" links will show
                                $('.commentList').each(function(){
                                    recordId = $(this).attr('id').substr('commentList'.length);
                                    refreshCommentList(recordId);
                                });

                                // if there is a followup action, then it should be processed
                                __dialogHandle.processFollowup = true;

                                // and we close the dialog
                                hideLightbox();
                            } else {
                                displayFormError($(form), response.data);
                            }
                        }
                    });
                } else {
                    displayFormError($(form), response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxSaveRecord() {
    $('#modalDialog > form[name="saveRecord"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var recordId = this.id.value;
        var url = path + '/AJAX/JSON?' + $.param({method:'saveRecord',id:recordId});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    // close the dialog
                    hideLightbox();
                    // Update user save statuses if the current context calls for it:
                    if (typeof(checkSaveStatuses) == 'function') {
                        checkSaveStatuses();
                    }
                    // Update tag list if appropriate:
                    if (typeof(refreshTagList) == 'function') {
                        refreshTagList(recordId);
                    }
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });

    $('a.listEdit').unbind('click').click(function(){
        var id = this.id.substr('listEdit'.length);
        hideLightbox();
        getLightbox('MyResearch', 'ListEdit', id, null, this.title, 'Record', 'Save', id);
        return false;
    });
}

function registerAjaxListEdit() {
    $('#modalDialog > form[name="listEdit"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var url = path + '/AJAX/JSON?' + $.param({method:'addList'});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    // if there is a followup action, then it should be processed
                    __dialogHandle.processFollowup = true;

                    // close the dialog
                    hideLightbox();
                } else if (response.status == 'NEED_AUTH') {
                    // TODO: redirect to login prompt?
                    // For now, we'll just display an error message; short of
                    // strange user behavior involving multiple open windows,
                    // it is very unlikely to get logged out at this stage.
                    displayFormError($form, response.data);
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxEmailRecord() {
    $('#modalDialog > form[name="emailRecord"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var url = path + '/AJAX/JSON?' + $.param({method:'emailRecord',id:this.id.value});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    // close the dialog
                    hideLightbox();
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxSMSRecord() {
    $('#modalDialog > form[name="smsRecord"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var url = path + '/AJAX/JSON?' + $.param({method:'smsRecord',id:this.id.value});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            clearForm: true,
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    // close the dialog
                    hideLightbox();
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxTagRecord() {
    $('#modalDialog > form[name="tagRecord"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var id = this.id.value;
        var url = path + '/AJAX/JSON?' + $.param({method:'tagRecord',id:id});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    hideLightbox();
                    refreshTagList(id);
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function refreshTagList(id) {
    $('#tagList').empty();
    var url = path + '/AJAX/JSON?' + $.param({method:'getRecordTags',id:id});
    $.ajax({
        dataType: 'json',
        url: url,
        success: function(response) {
            if (response.status == 'OK') {
                $.each(response.data, function(i, tag) {
                    var href = path + '/Search/Results?' + $.param({tag:tag.tag});
                    var html = (i>0 ? ', ' : ' ') + '<a href="' + href + '">' + tag.tag +'</a> (' + tag.cnt + ')';
                    $('#tagList').append(html);
                });
            } else if (response.data && response.data.length > 0) {
                $('#tagList').append(response.data);
            }
        }
    });
}

function registerAjaxCommentRecord() {
    $('form[name="commentRecord"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var form = this;
        var id = form.id.value;
        var url = path + '/AJAX/JSON?' + $.param({method:'commentRecord',id:id});
        $(form).ajaxSubmit({
            url: url,
            dataType: 'json',
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    refreshCommentList(id);
                    $(form).resetForm();
                } else if (response.status == 'NEED_AUTH') {
                    $dialog = getLightbox('AJAX', 'Login', id, null, 'Login');
                    $dialog.dialog({
                        close: function(event, ui) {
                            // login dialog is closed, check to see if we can proceed with followup
                            if (__dialogHandle.processFollowup) {
                                 // trigger the submit event on the comment form again
                                 $(form).trigger('submit');
                            }
                        }
                    });
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function refreshCommentList(recordId) {
    var url = path + '/AJAX/JSON?' + $.param({method:'getRecordCommentsAsHTML',id:recordId});
    $.ajax({
        dataType: 'json',
        url: url,
        success: function(response) {
            if (response.status == 'OK') {
                $('#commentList' + recordId).empty();
                $('#commentList' + recordId).append(response.data);
                $('#commentList' + recordId + ' a.deleteRecordComment').unbind('click').click(function() {
                    var commentId = $(this).attr('id').substr('recordComment'.length);
                    deleteRecordComment(recordId, commentId);
                    return false;
                });
            }
        }
    });
}

function deleteRecordComment(recordId,commentId) {
    var url = path + '/AJAX/JSON?' + $.param({method:'deleteRecordComment',id:commentId});
    $.ajax({
        dataType: 'json',
        url: url,
        success: function(response) {
            if (response.status == 'OK') {
                refreshCommentList(recordId);
            }
        }
    });
}

function registerAjaxEmailSearch() {
    $('#modalDialog > form[name="emailSearch"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var url = path + '/AJAX/JSON?' + $.param({method:'emailSearch'});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            data: {url:window.location.href},
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    hideLightbox();
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function displayFormError($form, error) {
    $form.parent().find('.error').remove();
    $form.prepend('<div class="error">' + error + '</div>');
}

// keep a handle to the current opened dialog so we can access it later
var __dialogHandle = {dialog: null, processFollowup:false, followupModule: null, followupAction: null, recordId: null};
function getLightbox(module, action, id, lookfor, message, followupModule, followupAction, followupId) {
    // Optional parameters
    if (followupModule === undefined) {followupModule = '';}
    if (followupAction === undefined) {followupAction = '';}
    if (followupId     === undefined) {followupId     = '';}

    var params = {
        method: 'getLightbox',
        lightbox: 'true',
        submodule: module,
        subaction: action,
        id: id,
        lookfor: lookfor,
        message: message,
        followupModule: followupModule,
        followupAction: followupAction,
        followupId: followupId
    };

    // create a new modal dialog
    $dialog = $('<div id="modalDialog"><div class="dialogLoading">&nbsp;</div></div>')
        .load(path + '/AJAX/JSON?' + $.param(params))
            .dialog({
                modal: true,
                autoOpen: false,
                closeOnEscape: true,
                title: message,
                width: 600,
                height: 350,
                close: function () {
                    // check if the dialog was successful, if so, load the followup action
                    if (__dialogHandle.processFollowup && __dialogHandle.followupModule
                            && __dialogHandle.followupAction && __dialogHandle.recordId) {
                        getLightbox(__dialogHandle.followupModule, __dialogHandle.followupAction,
                                __dialogHandle.recordId, null, message);
                    }
                }
            });

    // save information about this dialog so we can get it later for followup processing
    __dialogHandle.dialog = $dialog;
    __dialogHandle.processFollowup = false;
    __dialogHandle.followupModule = followupModule;
    __dialogHandle.followupAction = followupAction;
    __dialogHandle.recordId = id;

    // done
    return $dialog.dialog('open');
}

function hideLightbox() {
    if (!__dialogHandle.dialog) {
        return false;
    }
    __dialogHandle.dialog.dialog('close');
}

function loadResolverLinks($target, openUrl) {
    $target.addClass('ajax_availability');
    var url = path + '/AJAX/JSON?' + $.param({method:'getResolverLinks',openurl:openUrl});
    $.ajax({
        dataType: 'json',
        url: url,
        success: function(response) {
            if (response.status == 'OK') {
                $target.removeClass('ajax_availability')
                    .empty().append(response.data);
            } else {
                $target.removeClass('ajax_availability').addClass('error')
                    .empty().append(response.data);
            }
        }
    });
}

function toggleMenu(elemId) {
    var elem = $("#"+elemId);
    if (elem.hasClass("offscreen")) {
        elem.removeClass("offscreen");
    } else {
        elem.addClass("offscreen");
    }
}

function moreFacets(name) {
    $("#more"+name).hide();
    $("#narrowGroupHidden_"+name).removeClass("offscreen");
}

function lessFacets(name) {
    $("#more"+name).show();
    $("#narrowGroupHidden_"+name).addClass("offscreen");
}

function filterAll(element) {
    //  Look for filters (specifically checkbox filters)
    $("#searchForm :input[type='checkbox'][name='filter[]']")
        .attr('checked', element.checked);
}

function extractParams(str) {
    var params = {};
    var classes = str.split(/\s+/);
    for(i = 0; i < classes.length; i++) {
        if (classes[i].indexOf(':') > 0) {
            var pair = classes[i].split(':');
            params[pair[0]] = pair[1];
        }
    }
    return params;
}

// return unique values from the given array
function uniqueValues(array) {
    var o = {}, i, l = array.length, r = [];
    for(i=0; i<l;i++) {
        o[array[i]] = array[i];
    }
    for(i in o) {
        r.push(o[i]);
    }
    return r;
}
