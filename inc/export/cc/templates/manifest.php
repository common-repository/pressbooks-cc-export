<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
?>
<manifest identifier="cctd0001"
		  xmlns="http://www.imsglobal.org/xsd/imsccv1p1/imscp_v1p1"
		  xmlns:lom="http://ltsc.ieee.org/xsd/imsccv1p1/LOM/resource"
		  xmlns:lomimscc="http://ltsc.ieee.org/xsd/imsccv1p1/LOM/manifest"
		  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		  xsi:schemaLocation="
  http://www.imsglobal.org/xsd/imsccv1p1/imscp_v1p1 http://www.imsglobal.org/profile/cc/ccv1p1/ccv1p1_imscp_v1p2_v1p0.xsd
  http://ltsc.ieee.org/xsd/imsccv1p1/LOM/resource http://www.imsglobal.org/profile/cc/ccv1p1/LOM/ccv1p1_lomresource_v1p0.xsd
  http://ltsc.ieee.org/xsd/imsccv1p1/LOM/manifest http://www.imsglobal.org/profile/cc/ccv1p1/LOM/ccv1p1_lommanifest_v1p0.xsd">
	<metadata>
		<schema>IMS Common Cartridge</schema>
		<schemaversion>1.1.0</schemaversion>
		<lomimscc:lom>
			<lomimscc:general>
				<lomimscc:title>
					<lomimscc:string
						language="<?php echo $lang; ?>"><?php echo $meta['pb_title']; ?></lomimscc:string>
				</lomimscc:title>
				<lomimscc:description>
					<lomimscc:string
						language="<?php echo $lang; ?>"><?php echo ( isset( $meta['pb_about_50'] ) ) ? $meta['pb_about_50'] : ''; ?></lomimscc:string>
					<?php unset( $meta['pb_about_50'] ); ?>
				</lomimscc:description>
			</lomimscc:general>
		</lomimscc:lom>
	</metadata>
	<organizations>
		<organization identifier="O_1" structure="rooted-hierarchy">
			<item identifier="I_1">
				<item identifier="book">
					<title><?php echo $meta['pb_title']; ?></title>
					<?php unset( $meta['pb_title'] ); ?>
					<?php echo "\n"; ?>
					<?php
					foreach ( $manifest as $key => $item ) {
						echo '<item identifier="I_' . $key . '" identifierref="R_' . $item['ID'] . '">' . "\n";
						echo '<title>' . $item['post_title'] . '</title>' . "\n";
						echo '</item>' . "\n";
					}
					?>
				</item>
			</item>
		</organization>
	</organizations>
	<resources>
		<?php
		foreach ( $images as $url => $image_name ) {
			echo '<resource identifier="R_' . $image_name . '" type="webcontent">' . "\n";
			echo '<file href="OEBPS/assets/' . $image_name . '"/>' . "\n";
			echo '</resource>' . "\n";
		}
		?>
		<?php
		foreach ( $media as $url => $media_name ) {
			echo '<resource identifier="R_' . $media_name . '" type="webcontent">' . "\n";
			echo '<file href="OEBPS/assets/' . $media_name . '"/>' . "\n";
			echo '</resource>' . "\n";
		}
		?>
		<?php
		foreach ( $manifest as $key => $item ) {
			echo '<resource identifier="R_' . $item['ID'] . '" type="webcontent" href="OEBPS/' . $item['filename'] . '">' . "\n";
			echo '<file href="OEBPS/' . $item['filename'] . '"/>' . "\n";
			echo '</resource>' . "\n";
		}
		?>
	</resources>
</manifest>
