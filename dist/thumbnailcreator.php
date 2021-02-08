#!/usr/bin/php
<?php

require dirname(__FILE__) . '/nano_photos_provider2.encoding.php';

function image_fix_orientation(&$image, &$size, $filename) {
  if (!preg_match('\.(jpg|JPG|jpeg|JPEG)$', $filename)) {
    // It's not a JPEG
    return;
  }
    
  $exif = exif_read_data($filename);
  if (!empty($exif['Orientation'])) {
    switch ($exif['Orientation']) {
      case 3:
        if ($image != null)
            $image = imagerotate($image, 180, 0);
        break;
      case 6:
        if ($image != null)
          $image = imagerotate($image, -90, 0);
        list($size[0],$size[1]) = array($size[1],$size[0]);
        break;
      case 8:
        if ($image != null)
          $image = imagerotate($image, 90, 0);
        list($size[0],$size[1]) = array($size[1],$size[0]);
        break;
    }
  }
}

$config_values = parse_ini_file( dirname(__FILE__) . '/nano_photos_provider2.cfg', true);

// Loop through all files in contentFolder
$path = realpath( dirname(__FILE__ ) . '/' .$config_values['config']['contentFolder'] );
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $filepath)
{
        // Only files with extensions according to fileExtensions
        if (preg_match("/".$config_values['config']['fileExtensions']."/iu",$filepath) and strpos($filepath, '_thumbnail') ===false) {
            
            $baseFolder = dirname($filepath);
            $imageFilename = basename($filepath);
            $thumbWidth = $config_values['thumbnails']['thumbnailWidth'];
            $thumbHeight = $config_values['thumbnails']['thumbnailHeight'];
            $thumbnailFilename     = pathinfo($filepath, PATHINFO_FILENAME) . "_" . $thumbWidth . "_" . $thumbHeight . "." . pathinfo($filepath, PATHINFO_EXTENSION);
            $dominantcolorFilename = $thumbnailFilename . ".data";
            
            // Check if _thumbnail - Folder exists
            if (!file_exists( $baseFolder . '/_thumbnails' )) {
                mkdir( $baseFolder . '/_thumbnails', 0755, true );
            }
            
            // Check if thumbnail exists
            $generateThumbnail = true;
            if (file_exists($baseFolder . '/_thumbnails/' . $thumbnailFilename)) {
                if( filemtime($baseFolder . '/_thumbnails/' . $thumbnailFilename) > filemtime($baseFolder.'/'.$imageFilename) ) {
                // image file is older as the thumbnail file
                $generateThumbnail = false;
                }
            }
            
            // Check if dominantColors exists
            $generateDominantColors = true;
            if( file_exists($baseFolder . '/_thumbnails/' . $dominantcolorFilename)) {
                if( filemtime($baseFolder . '/_thumbnails/' . $dominantcolorFilename) > filemtime($baseFolder.'/'.$imageFilename) ) {
                $generateDominantColors=false;
                }
            }
            else {
            }
            
            // Get ImageSize
            $size = getimagesize($filepath);
            $orgImage = null;
            image_fix_orientation($orgImage, $size, $filepath);
                                
            $width  = $size[0];
            $height = $size[1];

            if ($height == 0) {
                echo "Image with zero height: $filepath\n";
                continue;
            }

            $originalAspect = $width / $height;
            $thumbAspect    = $thumbWidth / $thumbHeight;
            
            // Check ImageSize against MaxSize
            $generateMaxSize = false;
            if ( $width > $config_values['images']['maxSize'] || $height > $config_values['images']['maxSize'] ) {
                if ( file_exists($baseFolder . '/_thumbnails/' .$imageFilename) == false ) {
                    $generateMaxSize = true;
                }
            }           
            
            // Generate Image
            if( $generateThumbnail == true || $generateDominantColors == true || $generateMaxSize == true ) {
                switch ($size['mime']) {
                    case 'image/jpeg':
                        $orgImage = imagecreatefromjpeg($filepath);
                        break;
                    case 'image/gif':
                        $orgImage = imagecreatefromgif($filepath);
                        break;
                    case 'image/png':
                        $orgImage = imagecreatefrompng($filepath);
                        break;
                    default:
                        return false;
                        break;
                }
            }
            image_fix_orientation($orgImage, $size, $filepath);
            
            // Resize Image if >MaxSize
            if( $generateMaxSize == true ) {
                
                // Calc new size
                if ( $width > $height ) {
                    $MaxSizeWidth = $config_values['images']['maxSize'];
                    $MaxSizeHeight = $MaxSizeWidth / $originalAspect;
                    
                } else {
                    $MaxSizeHeight = $config_values['images']['maxSize'];
                    $MaxSizeWidth = $MaxSizeHeight * $originalAspect;
                }
                
                $MaxSize = imagecreatetruecolor($MaxSizeWidth, $MaxSizeHeight);
                // Resize
                imagecopyresampled($MaxSize, $orgImage, 0, 0, 0, 0, $MaxSizeWidth, $MaxSizeHeight, $width, $height);
                
                switch ($size['mime']) {
                  case 'image/jpeg':
                    imagejpeg($MaxSize, $baseFolder . '/_thumbnails/' . $imageFilename, $config_values['thumbnails']['jpegQuality'] );
                    break;
                  case 'image/gif':
                    imagegif($MaxSize, $baseFolder . '/_thumbnails/' . $imageFilename);
                    break;
                  case 'image/png':
                    imagepng($MaxSize, $baseFolder . '/_thumbnails/' . $imageFilename, 1);
                    break;
                }
            }

            if ( $thumbWidth != 'auto' && $thumbHeight != 'auto' ) {
                
                // IMAGE CROP
                // some inspiration found in donkeyGallery (from Gix075) https://github.com/Gix075/donkeyGallery 
                if ($originalAspect >= $thumbAspect) {
                  // If image is wider than thumbnail (in aspect ratio sense)
                  $newHeight = $thumbHeight;
                  $newWidth  = $width / ($height / $thumbHeight);
                } else {
                  // If the thumbnail is wider than the image
                  $newWidth  = $thumbWidth;
                  $newHeight = $height / ($width / $thumbWidth);
                }
                
                if( $generateThumbnail == true ) {
                  $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
                  // Resize and crop
                  imagecopyresampled($thumb, $orgImage,
                        0 - ($newWidth - $thumbWidth) / 2,    // dest_x: Center the image horizontally
                        0 - ($newHeight - $thumbHeight) / 2,  // dest-y: Center the image vertically
                        0, 0, // src_x, src_y
                        $newWidth, $newHeight, $width, $height);
                }
            
            } else {
                // NO IMAGE CROP
                if( $thumbWidth == 'auto' ) {
                  $newWidth  = $width / $height * $thumbHeight;
                  $newHeight = $thumbHeight;
                }
                else {
                  $newHeight = $height / $width * $thumbWidth;
                  $newWidth  = $thumbWidth;
                }
                
                if( $generateThumbnail == true ) {
                  $thumb = imagecreatetruecolor($newWidth, $newHeight);

                  // Resize
                  imagecopyresampled($thumb, $orgImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                }
            }
            
            if( $generateThumbnail == true ) {
                switch ($size['mime']) {
                  case 'image/jpeg':
                    imagejpeg($thumb, $baseFolder . '/_thumbnails/' . $thumbnailFilename, $config_values['thumbnails']['jpegQuality'] );
                    break;
                  case 'image/gif':
                    imagegif($thumb, $baseFolder . '/_thumbnails/' . $thumbnailFilename);
                    break;
                  case 'image/png':
                    imagepng($thumb, $baseFolder . '/_thumbnails/' . $thumbnailFilename, 1);
                    break;
                }
            }
            
            
            if( $generateDominantColors == true ) {
                // Dominant colorS -> GIF
                $dc3 = imagecreate($config_values['thumbnails']['blurredImageQuality'], $config_values['thumbnails']['blurredImageQuality']);
                imagecopyresampled($dc3, $orgImage, 0, 0, 0, 0, 3, 3, $width, $height);
                ob_start(); 
                imagegif( $dc3 );
                $image_data = ob_get_contents(); 
                ob_end_clean();
        
                // Dominant color -> HEX RGB
                $pixel = imagecreatetruecolor(1, 1);
                imagecopyresampled($pixel, $orgImage, 0, 0, 0, 0, 1, 1, $width, $height);
                $rgb = imagecolorat($pixel, 0, 0);
                $color = imagecolorsforindex($pixel, $rgb);
                $hex=sprintf('#%02x%02x%02x', $color['red'], $color['green'], $color['blue']);
                                
                // save to cache
                $fdc = fopen($baseFolder . '/_thumbnails/' . $thumbnailFilename . '.data', 'w');
                if( $fdc ) { 
                    fwrite($fdc, 'dc=' . $hex . "\n");
                    fwrite($fdc, 'dcGIF=' . base64_encode( $image_data ));
                    fclose($fdc);
                }
                else {
                }
            }
        }
}
?>
