	
<% if Posts %>
<% loop Posts %>
	<div class="microPost" data-id="$ID" data-parent="$ParentID" id="post$ID" data-rating="$WilsonRating">
		<div class="microPostContent">
			<% include PostContent %>
			
			<p class="postOptions">
				<span class="upCount">$Up</span> - <span class="downCount">$Down</span>
				<a href="#" class="vote" data-dir="1" data-id="$ID">Up</a>
				<a href="#" class="vote" data-dir="-1" data-id="$ID">Down</a>
				
				<% if Top.ShowReplies %>
					<a href="#" class="replyToPost">reply</a>
					<% if Deleted %>
					<% else %>
						<% if checkPerm('Delete') %>
						<a href="#" class="deletePost">delete</a>
						<% end_if %>
					<% end_if %>
				
				<% else %>
				<a href="$Owner.Link?post=$ID">replies</a>
					
				<% end_if %>
				
			</p>
			<!-- note that the action is left blank and filled in with JS because otherwise the
				recursive template loses context of what to fill in, so we use our top level form -->
			<form method="POST" action="" class="replyForm">
				<input type="hidden" value="$SecurityID" name="SecurityID" />
				<input type="hidden" name="ParentID" value="$ID" />
				<textarea placeholder="Add reply..." name="Content" class="expandable postContent"></textarea>
				<input type="submit" value="Reply" name="action_savepost" />
			</form>
			
			<div class="postReplies">
				<% if Top.ShowReplies %>
				<% if Replies %>
				<% include Timeline ShowReplies=$Top.ShowReplies %>
				<% end_if %>
				<% end_if %>
			</div>
		</div>
	</div>
	<% end_loop %>
<% end_if %>