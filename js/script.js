/*jslint browser: true, continue: true, regexp: true, plusplus: true, sloppy: true */
/*global $RTC */
/*global jQuery */
/*global console */

$RTC.get_parent_container = function (parent_id) {
    var parent_object = false,
        parent_container = false,
        is_root = false;
    if (parent_id > 0) {
        if (jQuery($RTC.comment_el + parent_id).length > 0) {
            // find parent set by default Walker
            parent_object = jQuery($RTC.comment_el + parent_id);
        } else if (jQuery("li#li-comment-" + parent_id).length > 0) {
            // find parent set by older Walker
            parent_object = jQuery("li#li-comment-" + parent_id);
        }
    }

    if (parent_object) {
        // parent object is set - lets comment as child
        if (parent_object.find('.' + $RTC.children_class).length === 0) {
            // children container does not exist, create
            parent_object.append('<ol class="' + $RTC.children_class + '"></ol>');
        }
        parent_container = parent_object.find('.' + $RTC.children_class);
    } else {
        // parent not set= it's either root level comment or parent comment is spam/trash
        // find (or create) root level comments container
        if (jQuery($RTC.comment_list_el + ' .' + $RTC.list_container_class).length > 0) {
            // search default root container
            parent_container = jQuery($RTC.comment_list_el + ' .' + $RTC.list_container_class);
        } else if (jQuery($RTC.comment_list_el + " .commentlist").length > 0) {
            // search root container created by old WP walker
            parent_container = jQuery($RTC.comment_list_el + " .commentlist");
        } else {
            // create root container
            // if #comments is not visible, does nothing
            parent_container = jQuery($RTC.comment_list_el).prepend('<ol class="' + $RTC.list_container_class + '"></ol>');
        }
        is_root = true;
    }
    return {container: parent_container, is_root: is_root};
};

$RTC.identify_theme = function () {
    /* Not sure, is it needed at all
       it's meant for updating comment_list_el, comment_el, list_container_class, children_class variables
       according to current Comments Walker. By default, values are set for default walker.
       Maybe those values should come from WP.
    */
    $RTC.comment_list_el = '#comments';
    $RTC.comment_el = 'li#comment-';
    $RTC.list_container_class = 'comment-list';
    $RTC.children_class = 'children';
};

$RTC.get_comment_container = function (comment_id) {
    var container = false;
    if (jQuery($RTC.comment_el + comment_id).length) {
        // new themes
        container = jQuery($RTC.comment_el + comment_id);
    } else if (jQuery("li#li-comment-" + comment_id).length) {
        // old themes
        container = jQuery("li#li-comment-" + comment_id);
    }
    return container;
};

$RTC.addComment = function (comment) {
    var parent = $RTC.get_parent_container(comment.parent),
        me = $RTC.get_comment_container(comment.id);
    if (comment.approved === '1' || comment.approved === 'approve') {
        if (me) {
            // @todo: it already exists, update article content
            me.innerHTML = comment.html;
        } else if (parent) {
            // exists parent, add to it
            if (parent.is_root && $RTC.order === 'desc') {
                parent.container.prepend(comment.html);
            } else {
                parent.container.append(comment.html);
            }
        }
    } else if (comment.approved === '0' || comment.approved === 'spam' || comment.approved === 'trash' || comment.approved === 'hold') {
          // delete
        if (me) {
            me.remove();
        }
    } else {
        console.log('Error: ' + comment.id + ' approved is ' + comment.approved + '?');
    }
};

$RTC.getComments = function () {
    var send = {
        'action': 'rtc_update',
        'rtc_bookmark': $RTC.bookmark,
        'postid': $RTC.postid
    },
        i;
    jQuery.ajax({
        url: $RTC.ajaxurl,
        data: send,
        dataType: 'json',
        type: 'post',
        cache: false,
        success: function (response) {
            $RTC.bookmark = response.bookmark;
            if (response.status === 200) {
                // populate comments
                // approve|hold|spam|trash
                for (i = 0; i < response.comments.length; i++) {
                    $RTC.addComment(response.comments[i]);
                }
            } else if (response.status === 304) {
                // console.debug('contents not modified, do nothing');
            } else {
                // not comments context, disable loop
                return false;
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.debug(textStatus + ': ' + errorThrown);
            return false;
        }
    });
    return true;
};

$RTC.init = function () {
    var success = true, interval;
    if ($RTC.refresh_interval > 100) {
        $RTC.identify_theme();
        interval = setInterval(function () {
            success = $RTC.getComments();
            if (!success) {
                clearInterval(interval);
                console.debug('stop');
            }
        }, $RTC.refresh_interval);
    }
};

jQuery(document).ready(function () { $RTC.init(); });
