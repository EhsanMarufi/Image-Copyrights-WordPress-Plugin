<?php
/*
Plugin Name: Image Copyright
Description: Inserts copyrights on images
Version: 1.0
Author: Ehsan Marufi
*/
defined( 'ABSPATH' ) or die( 'No direct access please!' );

// Runs when plug-in is activated and creates new database field
register_activation_hook(__FILE__,'image_copyrights_plugin_install');
function image_copyrights_plugin_install() {
}

// load the CSS file
add_action( 'init', 'image_copyrights_plugin_enqueue_css' );
function image_copyrights_plugin_enqueue_css() {
	//wp_register_style('image-copyrights-stylesheet', plugins_url('image-copyrights/image-copyrights-plugin-style.css'));
	//wp_enqueue_style('image-copyrights-stylesheet');
}

// Add the menu item to the WordPress menu
add_action( 'admin_menu', 'register_image_copyrights_custom_menu_page' );
function register_image_copyrights_custom_menu_page() {
	// The plugin is available for those who have the 'upload_files' capability (http://codex.wordpress.org/Roles_and_Capabilities)
	add_menu_page( 'Image Copyright', 'Image Copyright', 'upload_files', 'image-copyrights', 'display_image_copyrights_admin_screen');
}

function display_image_copyrights_admin_screen() {
	$current_page_address = $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
	
	if(isset($_POST['operation'])) {
		$msgs = array();
		
		switch($_POST['operation']) {
			case 'file-upload':
			
				// The idiot 'media_handle_upload' expects the first argument to be the name of a single file name (not an array)
				// So, we need to fool the function to do the job, disguising the $_FILES each time to point to a single file!!
				$attachment_ids = array();
				$files = $_FILES['file-to-upload'];
				foreach ($files['name'] as $key => $value) {
					if ($files['name'][$key]) {
						$file = array(
							'name' => $files['name'][$key],
							'type' => $files['type'][$key], 
							'tmp_name' => $files['tmp_name'][$key],
							'error' => $files['error'][$key],
							'size' => $files['size'][$key]
						); 
						$_FILES = array ('file-to-upload' => $file);
						foreach ($_FILES as $file => $array) {
							$attachment_id = media_handle_upload('file-to-upload', 0);
							if(!is_wp_error( $attachment_id )) {
								$attachment_ids[] = $attachment_id;
								$msgs[] = 'File "'.$array['name'].'" is valid and uploaded successfully.';
							}
							else
								$msgs[] = 'Error while uploading file ' . $array['name'];
						}
					} 
				}
				
				// inserting the image copyrights to the every successfully uploaded image
				foreach($attachment_ids as $attachment_id) {
					echo "\n";
					$sizes = array_merge(array('full'),get_intermediate_image_sizes());
					$uploads = wp_upload_dir();
					
					foreach ($sizes as $imageSize) {
						// Get the image object
						$image_object = wp_get_attachment_image_src($attachment_id, $imageSize);
						// Isolate the url
						$image_url = $image_object[0];
						// Using the wp_upload_dir replace the baseurl with the basedir
						$image_path = str_replace( $uploads['baseurl'], $uploads['basedir'], $image_url );
						//echo   $imageSize . ':<br>' .$image_path.'<br>';
						
						$left_aligned_text = empty($_POST['photographer-name']) ? '' : "Photo by: {$_POST['photographer-name']}";
						$right_aligned_text = empty($_POST['website-name']) ? '' : $_POST['website-name'];
						$logo_image_path = __DIR__ . '/logo.png';
						switch($imageSize) {
							case 'thumbnail':
								$left_aligned_text = '';
								$logo_image_path = null;
								break;
						}
						add_image_copyrights($image_path, $logo_image_path, $left_aligned_text, $right_aligned_text);
					}
				}
			break;
		}
	foreach($msgs as $msg)
		echo "<div>$msg</div>";
	}

	?>
	<h1>Upload files with copyrights attached</h1>
	<form action="<?php echo $current_page_address; ?>" method="post"  enctype="multipart/form-data">
		<div><label for="photographer-name">Photographer:</label><input type="text" name="photographer-name" id="photographer-name" placeholder="Name & FamilyName" /></div>
		<div><label for="website-name">Website:</label><input type="text" name="website-name" id="website-name" placeholder="Website Name" /></div>
		<input type="file" name="file-to-upload[]" multiple><br/>
		<input type="hidden" name="operation" value="file-upload" />
		<input type="submit" value="Upload">
	</form>
	<?php
}


class ImageHelper {
	const IMAGETYPE_UNKNOWN = -1;
	const IMAGETYPE_JPEG = 0;
	const IMAGETYPE_PNG  = 1;
	const IMAGETYPE_GIF  = 2;
	
	private $image_type = ImageHelper::IMAGETYPE_UNKNOWN;
	private $img = null;
	
	public function get_image_type() { return $this->image_type; }
	public function &get_image() { return $this->img; }
	
	public function __construct($filename) {
		if (!file_exists($filename)) {
			throw new InvalidArgumentException('File "'.$filename.'" not found.');
		}
		switch ( strtolower( pathinfo( $filename, PATHINFO_EXTENSION ))) {
			case 'jpeg':
			case 'jpg':
				$this->image_type = ImageHelper::IMAGETYPE_JPEG;
				$this->img = imagecreatefromjpeg($filename);
			break;

			case 'png':
				$this->image_type = ImageHelper::IMAGETYPE_PNG;
				$this->img = imagecreatefrompng($filename);
				$this->preserve_transparency();
			break;

			case 'gif':
				$this->image_type = ImageHelper::IMAGETYPE_GIF;
				$this->img = imagecreatefromgif($filename);
				$this->preserve_transparency();
			break;

			default:
				throw new InvalidArgumentException('File "'.$filename.'" is not valid jpg, png or gif image.');
			break;
		}
	}
	
	public function __destruct() {
		//$this->image_destroy();
	}
	
	private function preserve_transparency() {
		if($this->image_type == ImageHelper::IMAGETYPE_GIF || $this->image_type == ImageHelper::IMAGETYPE_PNG) {
			//imagecolortransparent($this->img, imagecolorallocatealpha($this->img, 0, 0, 0, 127));
			imagesavealpha($this->img, true);
		}
  }
	
	public function save_image($file_path) {
		$save_result = null;
		if(isset($this->img)) {
			switch($this->image_type) {
				case ImageHelper::IMAGETYPE_JPEG:
					$save_result = imagejpeg($this->img, $file_path);
					break;
				case ImageHelper::IMAGETYPE_PNG:
					$save_result = imagepng($this->img, $file_path);
					break;
				case ImageHelper::IMAGETYPE_GIF:
					$save_result = imagegif($this->img, $file_path);
					break;
				default:
			}
		}
		return $save_result;
	}
	
	public function image_destroy() {
		if(isset($this->img))
			imagedestroy($this->img);
		$this->image_type = ImageHelper::IMAGETYPE_UNKNOWN;
	}
}

function add_image_copyrights($source_image_path, $logo_image_path, $left_aligned_text, $right_aligned_text, $options = null) {
	$default_options = array(
		'rectangle_height' => 24,
		'rectangle_colors'=>array(0, 0, 0), // RGB
		'rectangle_opacity_percent'=> 50, 
		'text_colors'=>array(255, 255, 255), // RGB
		'font_size' => 10,
		'padding' => 7.5,
		'font_path' => __DIR__ . '/arialbd.ttf',
	);
	
	$rectangle_height = $default_options['rectangle_height'];
	$text_colors = $default_options['text_colors'];
	$rectangle_colors = $default_options['rectangle_colors'];
	$rectangle_opacity_percent = $default_options['rectangle_opacity_percent'];
	$font_size = $default_options['font_size'];
	$padding = $default_options['padding'];
	$font_path = $default_options['font_path'];
	
	if(isset($options['rectangle_height']))
		$rectangle_height = $options['rectangle_height'];
	
	if(isset($options['text_colors']))
		$text_colors = $options['text_colors'];
	
	if(isset($options['rectangle_colors']))
		$rectangle_colors = $options['rectangle_colors'];
	
	if(isset($options['rectangle_opacity_percent']))
		$rectangle_opacity_percent = $options['rectangle_opacity_percent'];
	
	if(isset($options['font_size']))
		$font_size = $options['font_size'];
	
	if(isset($options['padding']))
		$padding = $options['padding'];
	
	if(isset($options['font_path']))
		$font_path = $options['font_path'];
	

	$img_helper = new ImageHelper($source_image_path);
	$im = $img_helper->get_image(); // returns by reference
	if(!$im) return false;
	
	$image_width = imagesx($im);
	$image_height = imagesy($im);
	
	$text_y = $image_height - $padding;
	
	// Draw the bottom rectangle 
	$rectangle_color = imagecolorallocatealpha($im, $rectangle_colors[0], $rectangle_colors[1], $rectangle_colors[2], 63); // (127*$rectangle_opacity_percent)/100
	imagealphablending($im, true);
	imagefilledrectangle($im, 0, $image_height - $rectangle_height - 50, $image_width, $image_height, $rectangle_color);
	imagecolordeallocate($im, $rectangle_color);
	
	$text_color = imagecolorallocate($im, $text_colors[0], $text_colors[1], $text_colors[2]);
	

	// Draw the text on the left margin of the bottom rectangle
	imagettftext($im, $font_size, 0, $padding, $text_y, $text_color, $font_path, $left_aligned_text);
	
	
	// Draw the text on the right margin of the bottom rectangle
	$right_aligned_text_dimensions = imagettfbbox($font_size, 0, $font_path, $right_aligned_text);
	$right_aligned_text_width = abs($right_aligned_text_dimensions[4] - $right_aligned_text_dimensions[0]);
	$right_aligned_text_x = $image_width - $right_aligned_text_width - $padding;
	imagettftext($im, $font_size, 0, $right_aligned_text_x, $text_y, $text_color, $font_path, $right_aligned_text);
	
	
	// Copy the logo image on top of the bottom rectangle
	if(isset($logo_image_path)) {
		$logo_img_helper = new ImageHelper($logo_image_path);
		$logo_im = $logo_img_helper->get_image(); // returns by reference
		if(!$logo_im) return false;
		
		$logo_width = imagesx($logo_im);
		$logo_height = imagesy($logo_im);
		imagecopy(
			$im, $logo_im,
			$image_width - $logo_width - $padding, $image_height - $logo_height - $rectangle_height - $padding,
			0, 0, $logo_width, $logo_height
		);
		$logo_img_helper->image_destroy();
	}
	// Save the image
	$img_helper->save_image($source_image_path);
	
	// Release the memory
	$img_helper->image_destroy();
	
	return true;
}
