jQuery(function(){
	var comment_list_el, comment_el, list_container_class, children_class;

	function identify_theme() {
		/* @todo: not sure, is it needed at all
		   it's meant for updating comment_list_el, comment_el, list_container_class, children_class variables
		   according to current Comments Walker. By default, values are set for default walker.
		*/
		comment_list_el='#comments';
		comment_el='li#comment-';
		list_container_class='comment-list';
		children_class='children';
	}

	function get_parent_container(parent_id) {
		var parent_object=false;
		var parent_container=false;
		if(parent_id>0) {
			if(jQuery(comment_el+parent_id).length>0) {
				// find parent set by default Walker
				parent_object=jQuery(comment_el+parent_id);
			} else if(jQuery("li#li-comment-"+parent_id).length>0) {
				// find parent set by older Walker
				parent_object=jQuery("li#li-comment-"+parent_id);
			}
		}

		if(parent_object) {
			// parent object is set - lets comment as child
			if(!parent_object.find('.'+children_class).length>0) {
				// children container does not exist, create
				parent_object.append('<ol class="'+children_class+'"></ol>');
			}
			parent_container=parent_object.find('.'+children_class);
		} else {
			// parent not set= it's either root level comment or parent comment is spam/trash
			// find (or create) root level comments container
			if(jQuery(comment_list_el+' .'+list_container_class).length>0) {
				// search default root container
				parent_container=jQuery(comment_list_el+' .'+list_container_class);
			} else if (jQuery(comment_list_el+" .commentlist").length>0) {
				// search root container created by old WP walker
				parent_container=jQuery(comment_list_el+" .commentlist");
			} else {
				// create root container
				// if #comments is not visible, does nothing
				parent_container=jQuery(comment_list_el).prepend('<ol class="'+list_container_class+'"></ol>');
			}
		} 	
		return parent_container;
	}

	function get_comment_container(comment_id) {
		if(jQuery(comment_el+comment_id).length) {
			// new themes
			return jQuery(comment_el+comment_id);
		} else if (jQuery("li#li-comment-"+comment_id).length) { 
			// old themes
			return jQuery("li#li-comment-"+comment_id);
		}
		return false;
	}

	if (rtc_refresh_time>100) {
		identify_theme();
		var interval = setInterval(function() {
			var data = {
				'action': 'rtc_update',
				'rtc_bookmark': rtc_bookmark,
				'postid': rtc_postid,
			};
			jQuery.post(ajaxurl, data, function(JSONstring) {
				if(JSONstring=='Ei') {
					// 'Ei' means: No changes.
					return;
				}
				try {
					response=jQuery.parseJSON(JSONstring);	
				} catch (e) {

					console.info('Unknown response: '+JSONstring);
					return;
				}
				// renew bookmark
				rtc_bookmark=response.bookmark;
				if(response.status==200) {
					// populate comments
					// approve|hold|spam|trash
					for(var i=0; i<response.comments.length;i++) {
						var comment=response.comments[i];
						if(comment.approved=='1' || comment.approved=='approve') {
							if(me=get_comment_container(comment.id) ) {
								// @todo: it already exists, update article content
								// me.article.innerHTML=comment.html.article.innerHTML
							} else if(parent_object=get_parent_container(comment.parent)) {
								// exists parent, add to it
								parent_object.append(comment.html);
							} else {
								// parent not found, e.g.
								// 1) comments section is hidden (it depends on theme), or
								// 2) there are no comments yet?
							}
						} else if (comment.approved=='0' || comment.approved=='spam' || comment.approved=='trash' || comment.approved=='hold') {
						  	// delete
							if(me=get_comment_container(comment.id)) {
								me.remove();
							}
						} else {
							alert('Error: '+comment.id+' approved is '+comment.approved+'?');
						}
					}
				} else if (response.status==500) {
					// not comments context, disable loop
					clearInterval(interval);
					return;
				} else if (response.status==304) {
					// contents not modified, do nothing
				}
				// console.info('Response: '+JSONstring);
			});
		}, rtc_refresh_time);
	}
});