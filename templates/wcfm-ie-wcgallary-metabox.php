<?php

// Featured Image by URL metabox Template

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

function wcfm_ie_get_gallary_slot( $image_url = '' ){
	ob_start();
	?>
	<?php if($image_url){ ?>
		<div id="wcfm_ie_wcgallary__COUNT__" class="wcfm_ie_wcgallary">
		<?php /*<div id="wcfm_ie_url_wrap__COUNT__" <?php if( $image_url != ''){ echo 'style="display: none;"'; } ?>>
			<input id="wcfm_ie_url__COUNT__" class="wcfm_ie_url" type="text" name="wcfm_ie_wcgallary[__COUNT__][url]" placeholder="Image URL" data-id="__COUNT__" value="<?php echo $image_url; ?>"/>
			<a id="wcfm_ie_preview__COUNT__" class="wcfm_ie_preview button" data-id="__COUNT__">
				Preview
			</a>
			</div> */ ?>
			<div id="wcfm_ie_img_wrap__COUNT__" class="wcfm_ie_img_wrap" <?php if( $image_url == ''){ echo 'style="display: none;"'; } ?>>
				<?php /* <span href="#" class="wcfm_ie_remove" data-id="__COUNT__"></span> */ ?>
				<img id="wcfm_ie_img__COUNT__" class="wcfm_ie_img" data-id="__COUNT__" src="<?php echo $image_url; ?>" />
			</div>
		</div>
	<?php } ?>
	<?php
	$gallery_image = ob_get_clean();
	return preg_replace('/\s+/', ' ', trim($gallery_image));
}

?>

<div id="wcfm_ie_wcgallary_metabox_content" >
	<?php
	global $WCFM_IE_MAINCLASS;
	$gallary_images = $WCFM_IE_MAINCLASS->wcfm_ie_get_wcgallary_meta( $post->ID );
	$count = 1;
	if( !empty( $gallary_images ) ){
		foreach ($gallary_images as $gallary_image ) {
			echo str_replace( '__COUNT__', $count, wcfm_ie_get_gallary_slot( $gallary_image['img_url'] ) );
			$count++;
		}
	}
	echo str_replace( '__COUNT__', $count, wcfm_ie_get_gallary_slot() );
	$count++;
	?>
</div>
<div style="clear:both"></div>
<?php /* <script>
	var counter = <?php echo $count;?>;
	var gallery_preview_images = document.querySelectorAll('.wcfm_ie_preview');
	for (var i = 0; i < gallery_preview_images.length; i++) {
		var current_element = gallery_preview_images[i];

		counter = counter + 1;
		var new_element_str = '';
		var id = current_element.getAttribute('data-id');
		imgUrl = document.getElementById('#wcfm_ie_url'+id).value;
		if ( imgUrl != '' ){
			// $("<img>", {
			// 	src: imgUrl,
			// 	error: function() { alert('Error URL Image') },
			// 	load: function() {
			// 		$('#wcfm_ie_img_wrap'+id).show();
			// 		$('#wcfm_ie_img'+id).attr('src',imgUrl);
			// 		$('#wcfm_ie_remove'+id).show();
			// 		$('#wcfm_ie_url'+id).hide();
			// 		$('#wcfm_ie_preview'+id).hide();
			// 		new_element_str = '<?php //echo wcfm_ie_get_gallary_slot(); ?>';
			// 		new_element_str = new_element_str.replace(/__COUNT__/g, counter );
			// 		$('#wcfm_ie_wcgallary_metabox_content').append( new_element_str );
			// 	}
			// });
		}

	}
</script> */ ?>