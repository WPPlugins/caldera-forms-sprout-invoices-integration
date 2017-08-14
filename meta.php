{{#if error}}
<p class="description">{{error}}</p>
{{else}}
{{#is meta_key value="estimate_id"}}
	{{#each ../../../entry}}
		{{#is meta_key value="invoice_id"}}
		<span id="sp_meta_toggle_{{meta_id}}" class="toggle_option_preview" style="float: right;">
			<button data-ref="#meta_panel_{{process_id}}_invoice_id" class="button button-primary" type="button">Invoice</button>
			<button data-ref="#meta_panel_{{process_id}}_estimate_id" class="button" type="button">Estimate</button>	
		</span>
		{{/is}}
	{{/each}}
{{/is}}
<div id="meta_panel_{{process_id}}_{{meta_key}}" class="meta_panel_{{process_id}}">
	<h2 class="cf_si_meta_title">{{title}}</h2>
	<a id="view_meta_{{meta_value}}" class="button" href="{{view_link}}" target="_blank"><?php _e('View', 'cf-sprout'); ?> {{title}}</a>
	<a id="edit_meta_{{meta_value}}" class="button right" href="{{edit_link}}" target="_blank"><?php _e('Edit', 'cf-sprout'); ?> {{title}}</a>
	{{{html}}}
</div>
{{/if}}

{{#script}}
//<script>
jQuery(function($){
	if($('#sp_meta_toggle_{{meta_id}}').length){
		$('#sp_meta_toggle_{{meta_id}}').on('click', 'button', function(e){
			e.preventDefault();
			var clicked = $(this);
			$('.meta_panel_{{process_id}}').hide();
			$('#sp_meta_toggle_{{meta_id}} button').removeClass('button-primary');
			clicked.addClass('button-primary');

			$(clicked.data('ref')).show();

		});
		$('#sp_meta_toggle_{{meta_id}} .button-primary').trigger('click');
	}
});


{{/script}}