<?php 
// Featured Image by URL metabox Template

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
$image_url = '';
$image_alt = '';
if( isset( $image_meta['img_url'] ) && $image_meta['img_url'] != '' ){
	$image_url = esc_url( $image_meta['img_url'] );
}
if( isset( $image_meta['img_alt'] ) && $image_meta['img_alt'] != '' ){
	$image_alt = esc_attr( $image_meta['img_alt'] );
}
?>
<?php if($image_url){ ?>
	<div id="wcfm_ie_metabox_content" >
	<?php /* <input id="wcfm_ie_url" type="text" name="wcfm_ie_url" placeholder="<?php _e('Image URL', 'featured-image-by-url') ?>" value="<?php echo $image_url; ?>" />
	<a id="wcfm_ie_preview" class="button" >
		<?php _e('Preview', 'featured-image-by-url') ?>
	</a>
	<input id="wcfm_ie_alt" type="text" name="wcfm_ie_alt" placeholder="<?php _e('Alt text (Optional)', 'featured-image-by-url') ?>" value="<?php echo $image_alt; ?>" /> */ ?>
	<div>
		<?php /* <span id="wcfm_ie_noimg">No image</span> */ ?>
		<img id="wcfm_ie_img" src="<?php echo $image_url; ?>" alt="<?php echo $image_alt; ?>" />
	</div>
	<?php /* <a id="wcfm_ie_remove" class="button" style="margin-top:4px;"><?php _e('Remove Image', 'featured-image-by-url') ?></a> */ ?>
</div>
<?php } ?>

<?php /*
<script>
	jQuery(document).ready(function($){

		<?php if ( ! $image_meta['img_url'] ): ?>
			$('#wcfm_ie_img').hide().attr('src','');
			$('#wcfm_ie_noimg').show();
			$('#wcfm_ie_alt').hide().val('');
			$('#wcfm_ie_remove').hide();
			$('#wcfm_ie_url').show().val('');
			$('#wcfm_ie_preview').show();
		<?php else: ?>
			$('#wcfm_ie_noimg').hide();
			$('#wcfm_ie_remove').show();
			$('#wcfm_ie_url').hide();
			$('#wcfm_ie_preview').hide();
		<?php endif; ?>

		// Preview Featured Image
		$('#wcfm_ie_preview').click(function(e){
			
			e.preventDefault();
			imgUrl = $('#wcfm_ie_url').val();
			
			if ( imgUrl != '' ){
				$("<img>", {
					src: imgUrl,
					error: function() {alert('<?php _e('Error URL Image', 'featured-image-by-url') ?>')},
					load: function() {
						$('#wcfm_ie_img').show().attr('src',imgUrl);
						$('#wcfm_ie_noimg').hide();
						$('#wcfm_ie_alt').show();
						$('#wcfm_ie_remove').show();
						$('#wcfm_ie_url').hide();
						$('#wcfm_ie_preview').hide();
					}
				});
			}
		});

		// Remove Featured Image
		$('#wcfm_ie_remove').click(function(e){

			e.preventDefault();
			$('#wcfm_ie_img').hide().attr('src','');
			$('#wcfm_ie_noimg').show();
			$('#wcfm_ie_alt').hide().val('');
			$('#wcfm_ie_remove').hide();
			$('#wcfm_ie_url').show().val('');
			$('#wcfm_ie_preview').show();

		});

	});

</script> */ ?>