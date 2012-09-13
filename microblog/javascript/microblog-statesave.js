
;(function ($) {
	
	var KEY = 'saved_state';
	
	var num = 1;
	
	$(function () {
		$.entwine('microblog', function ($) {
			
			$('input[name=action_savepost]').entwine({
				onmatch: function () {
					$(this).click(function () {
						localStorage.removeItem(KEY);
					})
				}
			})

			$('.postContent').entwine({
				onmatch: function () {
					var _this = this;
					// explicit focus bind because focusin gets called twice...!
					$(this).focus(function () {
						var current = localStorage.getItem(KEY);
						Microblog.log("monitoring " + num + ' for content ' + current);
						if (!current || $(this).val().length) {
							return;
						}
						if (current == $(this).val()) {
							return;
						}
						$(this).val(current);
						localStorage.removeItem(KEY);
						_this.checkContentSize();
					})
					
					this._super();
				},
				onkeyup: function () {
					localStorage.setItem(KEY, $(this).val());
				}
			});

		});
	})
})(jQuery);