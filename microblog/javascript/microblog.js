
window.Microblog = window.Microblog || {};
Microblog.Member = {};

;(function($) {
	$(function () {
		if ($('#MemberDetails').length) {
			var member = $('#MemberDetails').data('member');
			Microblog.Member = member ? member : {};
		}

		if ($('input[name=Search]').length) {
			var curVal = $('input[name=Search]').val();
			$('input[name=Search]').focus(function () {
				if ($(this).val() == curVal) {
					$(this).val('');
				}
			})
		}
		
		$('form.ajaxsubmitted').entwine({
			onmatch: function () {
				$(this).ajaxForm({
					success: function (data, status, jqxhr, form) {
						form.html($(data).html());
						form.find('input[type=submit]').effect('highlight')
					}
				});
			}
		})
		
		$('form.dashletreload').entwine({
			onmatch: function () {
				$(this).ajaxForm({
					success: function (data, status, jqxhr, form) {
						$(form).parents('div.dashlet').refresh();
					}
				})
			}
		})
	})
})(jQuery);