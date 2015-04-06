/*jslint browser: true, continue: true, regexp: true, plusplus: true, sloppy: true */
/*global $RTC */
/*global jQuery */
/*global console */

$RTC.discoverTheme = function () {
    var parent_found = false;
    if (typeof $RTC.comment_list_class !== 'string') {
        if (jQuery($RTC.comment_list_el + ' .comment-list').length > 0) {
            // theme based on twentyeleven or newer themes
            $RTC.comment_list_class = 'comment-list';
            $RTC.comment_tag = 'li';
            $RTC.comment_id_prefix = '#comment-';
            parent_found = true;
        } else if (jQuery($RTC.comment_list_el +' .commentlist').length > 0) {
            // theme based on twentyten
            $RTC.comment_list_class = 'commentlist';
            $RTC.comment_list_tag = 'ol';
            $RTC.comment_tag = 'li';
            $RTC.comment_id_prefix = '#li-comment-';
            parent_found = true;
        }
    } else if (jQuery($RTC.comment_list_el).children('.' + $RTC.comment_list_class).length > 0) {
        parent_found = true;
    }

    if (!parent_found) {
        // create root container
        if (typeof $RTC.comment_list_class !== 'string') {
            $RTC.comment_list_class = 'comment-list';
        }
        jQuery($RTC.comment_list_el).prepend('<' + $RTC.comment_list_tag + ' class="' + $RTC.comment_list_class + '"></' + $RTC.comment_list_tag + '>');
    }
    $RTC.container = jQuery($RTC.comment_list_el + ' .' + $RTC.comment_list_class);
    console.log('Comment-list container is ' + $RTC.container);
}

$RTC.get_comment_container = function (comment_id) {
    // returns 'li' type of object, false if not found
    // purpose: modify, append or remove 'li' object content
    var container = false;
    if (jQuery($RTC.comment_tag + $RTC.comment_id_prefix + comment_id).length > 0) {
        container = jQuery($RTC.comment_tag + $RTC.comment_id_prefix + comment_id);
    }
    return container;
};

$RTC.get_parent_container = function (parent_id) {
    // returns 'ol' type of container object, additionally 'is_root' parameter
    // purpose: get container where to add {append|prepend} child nodes (type of 'li')
    var parent_object = false,
        parent_container = false,
        is_root = false;
    if (parent_id > 0) {
        parent_object = $RTC.get_comment_container(parent_id);
        if (typeof parent_object === 'object') {
            if (parent_object.find('.' + $RTC.children_class).length === 0) {
                // children container does not exist, create
                parent_object.append('<' + $RTC.comment_list_tag + ' class="' + $RTC.children_class + '"></' + $RTC.comment_list_tag + '>');
            }
            parent_container = parent_object.find('.' + $RTC.children_class);
        } else {
            return false;
        }

    } else {
        is_root = true;
        parent_container = $RTC.container;
    }

    return {container: parent_container, is_root: is_root};
};


$RTC.addComment = function (comment) {
    var parent = $RTC.get_parent_container(comment.parent),
        me = $RTC.get_comment_container(comment.id);
    if (comment.approved === '1' || comment.approved === 'approve') {
        if (me) {
            // vaja teha: it already exists, update article content
            // me.innerHTML = comment.html;
        } else if (typeof parent === 'object') {
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

$RTC.countComments = function () {
    // .children() method differs from .find() in that .children() only travels a single level down the DOM tree
    return jQuery('.' + $RTC.comment_list_class).children($RTC.comment_tag).length;
};

$RTC.removeOldestComment = function () {
    if ($RTC.order === 'desc') {
        jQuery($RTC.container).children($RTC.comment_tag).last().remove();
    } else {
        jQuery($RTC.container).children($RTC.comment_tag).first().remove();
    }
    console.log('removed one oldest comment');
}

$RTC.paginate = function () {
    var numcomments = $RTC.countComments(), i;
    // tambov is here to avoid posting user to land to white page with only one comment
    // not sure if needed
    if ($RTC.comments_per_page > 0) {
        for (i = 0; i < (numcomments - $RTC.tambov - $RTC.comments_per_page); i++) {
            $RTC.removeOldestComment();
        }
    }
}

$RTC.getComments = function () {
    var send = {
        'action': 'rtc_update',
        'rtc_bookmark': $RTC.bookmark,
        'postid': $RTC.postid,
        'max_c_id': $RTC.max_c_id
    },
        i;
    jQuery.ajax({
        url: $RTC.ajaxurl,
        data: send,
        dataType: 'json',
        // dataType: 'text',
        type: 'post',
        cache: false,
        success: function (response) {
            // console.debug(response);
            $RTC.bookmark = response.bookmark;
            if (typeof response.max_c_id === 'string') {
                $RTC.max_c_id = response.max_c_id;
            }
            if (response.status === 200) {
                // populate comments
                // approve|hold|spam|trash
                for (i = 0; i < response.comments.length; i++) {
                    if (response.comments[i].parent > 0 || $RTC.is_last_page === '1') {
                        // to last page add all comments
                        // to not attempt to add root level comments to older comments pages
                        $RTC.addComment(response.comments[i]);
                        if ($RTC.is_last_page === '1') {
                            // paginate is needed only on "newest comments" page, because others cannot have new toplevel comments
                            $RTC.paginate();
                        }
                    }

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
    console.log('Is last page ' + $RTC.is_last_page);
    $RTC.discoverTheme();
    $RTC.refresh_interval = parseInt($RTC.refresh_interval, 10);
    console.log('This page has ' + $RTC.countComments() + ' toplevel comments');
    // new toplevel comments must be added only to "newest comments" page
    if ($RTC.refresh_interval > 100) {
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
