(function ($) {
	$(document).ready(function () {
		var debounceTimeout = null;

		// request the search from the server
		function requestresults(inputfield, datalist, _loadingdiv) {
			var options = {};
			_loadingdiv.html('Loading');

			options.url = inputfield.attr('data-url');
			options.type = "GET";
			options.data = {"query": inputfield.val()};
			options.dataType = "json";

			// ajax the search query
			options.success = function (data) {
				datalist.empty();
				for (var i = 0; i < data.length; i++) {
					datalist.append("<option value='" + data[i].id + "'>" + data[i].name + "</option>");
				}
				_loadingdiv.html('');
			};
			$.ajax(options);
		}

		function inputaction() {
			var _this = $(this);
			var _datalist = $("#" + $(this).attr('list'));
			var _loadingdiv = $("#" + $(this).attr('list') + '_loading');
			
			// populate the input select with the friendly nice looking value
			var option = _datalist.find('option[value="'+_this.val()+'"]').first();
			
			if(_this.val() && option.length === 1) {
				var friendlycontent = option.html();
				var friendlyval = option.val();
				
				$('[name="'+_this.attr('data-populate-id')+'"]').val(friendlyval);
				_this.val(friendlycontent);
				
				return true;
			}

			// debounce the request, so we're not smashing the server
			clearTimeout(debounceTimeout);
			debounceTimeout = setTimeout(function () {
				requestresults(_this, _datalist, _loadingdiv);
			}, 500);
		}

		// if in the CMS, rely on global bubbles
		if ($('body.cms').length) {
			$('body.cms').on('input', 'input.datalistautocompletefield', inputaction);
		} else {
			$('input.datalistautocompletefield').on('input', inputaction);
		}

	});
})(jQuery);