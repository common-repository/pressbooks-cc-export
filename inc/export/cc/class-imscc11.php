<?php
/**
 * Project: pressbooks-cc-export
 * Project Sponsor: BCcampus <https://bccampus.ca>
 * Copyright 2012-2017 Brad Payne
 * Date: 2017-11-20
 * Licensed under GPLv3, or any later version
 *
 * @author Brad Payne
 * @package Pressbooks_Cc_Export
 * @license https://www.gnu.org/licenses/gpl-3.0.txt
 * @copyright (c) 2012-2017, Brad Payne
 */

namespace BCcampusCC\Export\CC;

use Masterminds\HTML5;
use Pressbooks;
use Pressbooks\Modules\Export\Epub\Epub3;
use Pressbooks\Sanitize;

class Imscc11 extends Epub3 {

	/**
	 * @var string
	 */
	protected $suffix = '.imscc';

	/**
	 * @var string
	 */
	protected $filext = 'html';

	/**
	 * @var string
	 */
	protected $dir = __DIR__;

	/**
	 * @var string
	 */
	protected $extraCss = null;

	/**
	 * @var string
	 */
	protected $generatorPrefix;

	/**
	 * @var string
	 */
	protected $errorLog = '';

	/**
	 * Imscc11 constructor.
	 *
	 * @param array $args
	 */
	function __construct( array $args ) {
		parent::__construct( $args );
		$this->generatorPrefix = __( 'Common Cartridge 1.1: ', 'pressbooks-cc-export' );

	}

	/**
	 * Mandatory convert method, create $this->outputPath
	 *
	 * @return bool
	 */
	function convert() {
		return parent::convert();
	}

	/**
	 * @return \Generator
	 */
	function convertGenerator(): \Generator {
		// Sanity check
		if ( empty( $this->tmpDir ) || ! is_dir( $this->tmpDir ) ) {
			$this->logError( '$this->tmpDir must be set before calling convert().' );

			return false;
		}

		yield 1 => $this->generatorPrefix . __( 'Initializing', 'pressbooks-cc-export' );

		// Convert
		yield 2 => $this->generatorPrefix . __( 'Preparing book contents', 'pressbooks-cc-export' );

		$metadata      = PressBooks\Book::getBookInformation();
		$book_contents = $this->preProcessBookContents( Pressbooks\Book::getBookContents() );

		// Set two letter language code
		if ( isset( $metadata['pb_language'] ) ) {
			list( $this->lang ) = explode( '-', $metadata['pb_language'] );
		}

		try {
			yield 5 => $this->generatorPrefix . __( 'Creating container', 'pressbooks-cc-export' );
			$this->createContainer();
			yield from $this->createWebContentGenerator( $book_contents, $metadata );
			$this->createManifest( $metadata );

		} catch ( \Exception $e ) {
			$this->logError( $e->getMessage() );

			return false;
		}

		yield 75 => $this->generatorPrefix . __( 'Saving file to exports folder', 'pressbooks-cc-export' );
		$filename = $this->timestampedFileName( $this->suffix );
		if ( ! $this->zipImscc( $filename ) ) {
			return false;
		}
		$this->outputPath = $filename;
		yield 80 => $this->generatorPrefix . __( 'Export successful', 'pressbooks-cc-export' );

	}

	/**
	 * @return \Generator
	 */
	function validateGenerator(): \Generator {
		yield 80 => $this->generatorPrefix . __( 'Validating file', 'pressbooks-cc-export' );
		$file = $this->tmpDir . '/imsmanifest.xml';

		$use_errors = libxml_use_internal_errors( true );
		$xml        = simplexml_load_file( $file );
		if ( false === $xml ) {
			$this->errorLog .= "### {$file} ### \n";
			foreach ( libxml_get_errors() as $error ) {
				$this->errorLog .= $error->message . "\n";
			}
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $use_errors );
		if ( ! empty( $this->errorLog ) ) {
			$this->logError( $this->errorLog );

			return false;
		}

		yield 90 => $this->generatorPrefix . __( 'Validation successful', 'pressbooks-cc-export' );
		yield 100 => $this->generatorPrefix . __( 'Finishing up', 'pressbooks-cc-export' );
	}

	/**
	 * @param $book_contents
	 * @param $metadata
	 *
	 * @return \Generator
	 * @throws \Exception
	 */
	protected function createWebContentGenerator( $book_contents, $metadata ) : \Generator {

		// Reset manifest
		$this->manifest = [];
		yield 30 => $this->generatorPrefix . __( 'Exporting Front Matter', 'pressbooks-cc-export' );
		$this->createFrontMatter( $book_contents, $metadata );
		yield 40 => $this->generatorPrefix . __( 'Exporting Parts and Chapters', 'pressbooks-cc-export' );
		$this->createPartsAndChapters( $book_contents, $metadata );
		yield 50 => $this->generatorPrefix . __( 'Exporting Back Matter', 'pressbooks-cc-export' );
		$this->createBackMatter( $book_contents, $metadata );

	}

	/**
	 * Common Cartridge doesn't seem to care about naming conventions for directories
	 * keep OEBPS (same as EPUB) for easier class inheritance
	 */
	protected function createContainer() {
		mkdir( $this->tmpDir . '/OEBPS' );
		mkdir( $this->tmpDir . '/OEBPS/assets' );

	}

	/**
	 * Nearly verbatim from class-epub201.php in pressbooks 4.4.0
	 * removed title and number to avoid duplicate rendering in LMS
	 * @copyright Pressbooks
	 *
	 * @param array $book_contents
	 * @param array $metadata
	 *
	 * @throws \Exception
	 */
	protected function createFrontMatter( $book_contents, $metadata ) {
		$front_matter_printf  = '<div class="front-matter %s" id="%s">';
		$front_matter_printf .= '<div class="ugc front-matter-ugc">%s</div>%s';
		$front_matter_printf .= '</div>';

		$vars = [
			'post_title'                  => '',
			'stylesheet'                  => $this->stylesheet,
			'post_content'                => '',
			'append_front_matter_content' => '',
			'isbn'                        => ( isset( $metadata['pb_ebook_isbn'] ) ) ? $metadata['pb_ebook_isbn'] : '',
			'lang'                        => $this->lang,
		];

		$i = $this->frontMatterPos;
		foreach ( $book_contents['front-matter'] as $front_matter ) {

			if ( ! $front_matter['export'] ) {
				continue; // Skip
			}

			$front_matter_id = $front_matter['ID'];
			$subclass        = $this->taxonomy->getFrontMatterType( $front_matter_id );

			if ( 'dedication' === $subclass || 'epigraph' === $subclass || 'title-page' === $subclass || 'before-title' === $subclass ) {
				continue; // Skip
			}

			if ( 'introduction' === $subclass ) {
				$this->hasIntroduction = true;
			}

			$slug                        = $front_matter['post_name'];
			$content                     = $this->kneadHtml( $front_matter['post_content'], 'front-matter', $i );
			$append_front_matter_content = $this->kneadHtml( apply_filters( 'pb_append_front_matter_content', '', $front_matter_id ), 'front-matter', $i );
			$short_title                 = trim( get_post_meta( $front_matter_id, 'pb_short_title', true ) );
			$subtitle                    = trim( get_post_meta( $front_matter_id, 'pb_subtitle', true ) );
			$author                      = trim( get_post_meta( $front_matter_id, 'pb_authors', true ) );

			if ( Pressbooks\Modules\Export\Export::shouldParseSubsections() === true ) {
				$sections = Pressbooks\Book::getSubsections( $front_matter_id );

				if ( $sections ) {
					$content = Pressbooks\Book::tagSubsections( $content, $front_matter_id );
				}
			}

			if ( $author ) {
				$content = '<h2 class="chapter-author">' . Sanitize\decode( $author ) . '</h2>' . $content;
			}

			if ( $subtitle ) {
				$content = '<h2 class="chapter-subtitle">' . Sanitize\decode( $subtitle ) . '</h2>' . $content;
			}

			if ( $short_title ) {
				$content = '<h6 class="short-title">' . Sanitize\decode( $short_title ) . '</h6>' . $content;
			}

			$section_license = $this->doSectionLevelLicense( $metadata, $front_matter_id );
			if ( $section_license ) {
				$append_front_matter_content .= $this->kneadHtml( $this->tidy( $section_license ), 'front-matter', $i );
			}

			$vars['post_title']                     = $front_matter['post_title'];
			$vars['post_content']                   = sprintf(
				$front_matter_printf,
				$subclass,
				$slug,
				$content,
				$var['append_front_matter_content'] = $append_front_matter_content,
				''
			);

			$file_id  = 'front-matter-' . sprintf( '%03s', $i );
			$filename = "{$file_id}-{$slug}.{$this->filext}";

			file_put_contents(
				$this->tmpDir . "/OEBPS/$filename",
				$this->loadTemplate( $this->dir . '/templates/html.php', $vars )
			);

			$this->manifest[ $file_id ] = [
				'ID'         => $front_matter['ID'],
				'post_title' => $front_matter['post_title'],
				'filename'   => $filename,
			];

			++ $i;
		}

		$this->frontMatterPos = $i;
	}

	/**
	 * Nearly verbatim from epub201.php in pressbooks 4.4.0
	 * removed title and numbering to avoid duplicate rendering in LMS
	 * @copyright Pressbooks
	 *
	 * @param array $book_contents
	 * @param array $metadata
	 *
	 * @throws \Exception
	 */
	protected function createPartsAndChapters( $book_contents, $metadata ) {

		$part_printf = '<div class="part %s" id="%s">%s</div>';

		$chapter_printf  = '<div class="chapter %s" id="%s">';
		$chapter_printf .= '<div class="ugc chapter-ugc">%s</div>%s';
		$chapter_printf .= '</div>';

		$vars = [
			'post_title'             => '',
			'stylesheet'             => $this->stylesheet,
			'post_content'           => '',
			'append_chapter_content' => '',
			'isbn'                   => ( isset( $metadata['pb_ebook_isbn'] ) ) ? $metadata['pb_ebook_isbn'] : '',
			'lang'                   => $this->lang,
		];

		// Parts, Chapters
		$i = 1;
		$j = 1;
		$c = 1;
		$p = 1;
		foreach ( $book_contents['part'] as $part ) {

			$invisibility = ( get_post_meta( $part['ID'], 'pb_part_invisible', true ) === 'on' ) ? 'invisible' : '';

			$part_printf_changed = '';
			$array_pos           = count( $this->manifest );
			$has_chapters        = false;

			// Inject introduction class?
			if ( ! $this->hasIntroduction && count( $book_contents['part'] ) > 1 ) {
				$part_printf_changed   = str_replace( '<div class="part %s" id=', '<div class="part introduction %s" id=', $part_printf );
				$this->hasIntroduction = true;
			}

			// Inject part content?
			$part_content = trim( $part['post_content'] );
			if ( $part_content ) {
				$part_content        = $this->kneadHtml( $this->preProcessPostContent( $part_content ), 'custom', $p );
				$part_printf_changed = str_replace( '</h1></div>%s</div>', '</h1></div><div class="ugc part-ugc">%s</div></div>', $part_printf );
			}

			foreach ( $part['chapters'] as $chapter ) {

				if ( ! $chapter['export'] ) {
					continue; // Skip
				}

				$chapter_printf_changed = '';
				$chapter_id             = $chapter['ID'];
				$subclass               = $this->taxonomy->getChapterType( $chapter_id );
				$slug                   = $chapter['post_name'];
				$content                = $this->kneadHtml( $chapter['post_content'], 'chapter', $j );
				$append_chapter_content = $this->kneadHtml( apply_filters( 'pb_append_chapter_content', '', $chapter_id ), 'chapter', $j );
				$short_title            = false; // Ie. running header title is not used in EPUB
				$subtitle               = trim( get_post_meta( $chapter_id, 'pb_subtitle', true ) );
				$author                 = trim( get_post_meta( $chapter_id, 'pb_authors', true ) );

				if ( Pressbooks\Modules\Export\Export::shouldParseSubsections() === true ) {
					$sections = Pressbooks\Book::getSubsections( $chapter_id );

					if ( $sections ) {
						$content = Pressbooks\Book::tagSubsections( $content, $chapter_id );
					}
				}

				if ( $author ) {
					$content = '<h2 class="chapter-author">' . Sanitize\decode( $author ) . '</h2>' . $content;
				}

				if ( $subtitle ) {
					$content = '<h2 class="chapter-subtitle">' . Sanitize\decode( $subtitle ) . '</h2>' . $content;
				}

				if ( $short_title ) {
					$content = '<h6 class="short-title">' . Sanitize\decode( $short_title ) . '</h6>' . $content;
				}

				// Inject introduction class?
				if ( ! $this->hasIntroduction ) {
					$chapter_printf_changed = str_replace( '<div class="chapter %s" id=', '<div class="chapter introduction %s" id=', $chapter_printf );
					$this->hasIntroduction  = true;
				}

				$section_license = $this->doSectionLevelLicense( $metadata, $chapter_id );
				if ( $section_license ) {
					$append_chapter_content .= $this->kneadHtml( $this->tidy( $section_license ), 'chapter', $j );
				}

				$n                                 = ( 'numberless' === $subclass ) ? '' : $c;
				$vars['post_title']                = $chapter['post_title'];
				$vars['post_content']              = sprintf(
					( $chapter_printf_changed ? $chapter_printf_changed : $chapter_printf ),
					$subclass,
					$slug,
					$content,
					$var['append_chapter_content'] = $append_chapter_content,
					''
				);

				$file_id  = 'chapter-' . sprintf( '%03s', $j );
				$filename = "{$file_id}-{$slug}.{$this->filext}";

				file_put_contents(
					$this->tmpDir . "/OEBPS/$filename",
					$this->loadTemplate( $this->dir . '/templates/html.php', $vars )
				);

				$this->manifest[ $file_id ] = [
					'ID'         => $chapter['ID'],
					'post_title' => $chapter['post_title'],
					'filename'   => $filename,
				];

				$has_chapters = true;

				$j ++;

				if ( 'numberless' !== $subclass ) {
					++ $c;
				}
			}

			if ( count( $book_contents['part'] ) === 1 && $part_content ) { // only part, has content
				$slug                 = $part['post_name'];
				$m                    = ( 'invisible' === $invisibility ) ? '' : $p;
				$vars['post_title']   = $part['post_title'];
				$vars['post_content'] = sprintf(
					( $part_printf_changed ? $part_printf_changed : $part_printf ),
					$invisibility,
					$slug,
					$part_content
				);

				$file_id  = 'part-' . sprintf( '%03s', $i );
				$filename = "{$file_id}-{$slug}.{$this->filext}";

				file_put_contents(
					$this->tmpDir . "/OEBPS/$filename",
					$this->loadTemplate( $this->dir . '/templates/html.php', $vars )
				);

				// Insert into correct pos
				$this->manifest = array_slice( $this->manifest, 0, $array_pos, true ) + [
					$file_id => [
						'ID'         => $part['ID'],
						'post_title' => $part['post_title'],
						'filename'   => $filename,
					],
				] + array_slice( $this->manifest, $array_pos, count( $this->manifest ) - 1, true );

				++ $i;
				if ( 'invisible' !== $invisibility ) {
					++ $p;
				}
			} elseif ( count( $book_contents['part'] ) > 1 ) { // multiple parts
				if ( $has_chapters ) { // has chapter
					$slug                 = $part['post_name'];
					$m                    = ( 'invisible' === $invisibility ) ? '' : $p;
					$vars['post_title']   = $part['post_title'];
					$vars['post_content'] = sprintf(
						( $part_printf_changed ? $part_printf_changed : $part_printf ),
						$invisibility,
						$slug,
						$part_content
					);

					$file_id  = 'part-' . sprintf( '%03s', $i );
					$filename = "{$file_id}-{$slug}.{$this->filext}";

					file_put_contents(
						$this->tmpDir . "/OEBPS/$filename",
						$this->loadTemplate( $this->dir . '/templates/html.php', $vars )
					);

					// Insert into correct pos
					$this->manifest = array_slice( $this->manifest, 0, $array_pos, true ) + [
						$file_id => [
							'ID'         => $part['ID'],
							'post_title' => $part['post_title'],
							'filename'   => $filename,
						],
					] + array_slice( $this->manifest, $array_pos, count( $this->manifest ) - 1, true );

					++ $i;
					if ( 'invisible' !== $invisibility ) {
						++ $p;
					}
				} else { // no chapter
					if ( $part_content ) { // has content
						$slug                 = $part['post_name'];
						$m                    = ( 'invisible' === $invisibility ) ? '' : $p;
						$vars['post_title']   = $part['post_title'];
						$vars['post_content'] = sprintf(
							( $part_printf_changed ? $part_printf_changed : $part_printf ),
							$invisibility,
							$slug,
							$part_content
						);

						$file_id  = 'part-' . sprintf( '%03s', $i );
						$filename = "{$file_id}-{$slug}.{$this->filext}";

						file_put_contents(
							$this->tmpDir . "/OEBPS/$filename",
							$this->loadTemplate( $this->dir . '/templates/html.php', $vars )
						);

						// Insert into correct pos
						$this->manifest = array_slice( $this->manifest, 0, $array_pos, true ) + [
							$file_id => [
								'ID'         => $part['ID'],
								'post_title' => $part['post_title'],
								'filename'   => $filename,
							],
						] + array_slice( $this->manifest, $array_pos, count( $this->manifest ) - 1, true );

						++ $i;
						if ( 'invisible' !== $invisibility ) {
							++ $p;
						}
					}
				}
			}

			// Did we actually inject the introduction class?
			if ( $part_printf_changed && ! $has_chapters ) {
				$this->hasIntroduction = false;
			}
		}
	}

	/**
	 * Nearly verbatim from class-epub201.php in pressbooks 4.4.0
	 * remove title and numbering to avoid duplicate rendering in LMS
	 * @copyright Pressbooks
	 *
	 * @param array $book_contents
	 * @param array $metadata
	 *
	 * @throws \Exception
	 */
	protected function createBackMatter( $book_contents, $metadata ) {

		$back_matter_printf  = '<div class="back-matter %s" id="%s">';
		$back_matter_printf .= '<div class="ugc back-matter-ugc">%s</div>%s';
		$back_matter_printf .= '</div>';

		$vars = [
			'post_title'                 => '',
			'stylesheet'                 => $this->stylesheet,
			'post_content'               => '',
			'append_back_matter_content' => '',
			'isbn'                       => ( isset( $metadata['pb_ebook_isbn'] ) ) ? $metadata['pb_ebook_isbn'] : '',
			'lang'                       => $this->lang,
		];

		$i = 1;
		foreach ( $book_contents['back-matter'] as $back_matter ) {

			if ( ! $back_matter['export'] ) {
				continue; // Skip
			}

			$back_matter_id             = $back_matter['ID'];
			$subclass                   = $this->taxonomy->getBackMatterType( $back_matter_id );
			$slug                       = $back_matter['post_name'];
			$content                    = $this->kneadHtml( $back_matter['post_content'], 'back-matter', $i );
			$append_back_matter_content = $this->kneadHtml( apply_filters( 'pb_append_back_matter_content', '', $back_matter_id ), 'back-matter', $i );
			$short_title                = trim( get_post_meta( $back_matter_id, 'pb_short_title', true ) );
			$subtitle                   = trim( get_post_meta( $back_matter_id, 'pb_subtitle', true ) );
			$author                     = trim( get_post_meta( $back_matter_id, 'pb_authors', true ) );

			if ( Pressbooks\Modules\Export\Export::shouldParseSubsections() === true ) {
				$sections = Pressbooks\Book::getSubsections( $back_matter_id );

				if ( $sections ) {
					$content = Pressbooks\Book::tagSubsections( $content, $back_matter_id );
				}
			}

			if ( $author ) {
				$content = '<h2 class="chapter-author">' . Sanitize\decode( $author ) . '</h2>' . $content;
			}

			if ( $subtitle ) {
				$content = '<h2 class="chapter-subtitle">' . Sanitize\decode( $subtitle ) . '</h2>' . $content;
			}

			if ( $short_title ) {
				$content = '<h6 class="short-title">' . Sanitize\decode( $short_title ) . '</h6>' . $content;
			}

			$section_license = $this->doSectionLevelLicense( $metadata, $back_matter_id );
			if ( $section_license ) {
				$append_back_matter_content .= $this->kneadHtml( $this->tidy( $section_license ), 'back-matter', $i );
			}

			$vars['post_title']                    = $back_matter['post_title'];
			$vars['post_content']                  = sprintf(
				$back_matter_printf,
				$subclass,
				$slug,
				$content,
				$var['append_back_matter_content'] = $append_back_matter_content,
				''
			);

			$file_id  = 'back-matter-' . sprintf( '%03s', $i );
			$filename = "{$file_id}-{$slug}.{$this->filext}";

			file_put_contents(
				$this->tmpDir . "/OEBPS/$filename",
				$this->loadTemplate( $this->dir . '/templates/html.php', $vars )
			);

			$this->manifest[ $file_id ] = [
				'ID'         => $back_matter['ID'],
				'post_title' => $back_matter['post_title'],
				'filename'   => $filename,
			];

			++ $i;
		}
	}

	/**
	 * Nearly verbatim from class-epub201.php from pressbooks 4.4.0
	 * eliminated Mobi Hack
	 * @copyright Pressbooks
	 *
	 * Pummel the HTML into IMSCC compatible dough.
	 *
	 * @param string $html
	 * @param string $type front-matter, part, chapter, back-matter, ...
	 * @param int $pos (optional) position of content, used when creating filenames like: chapter-001, chapter-002, ...
	 *
	 * @return string
	 */
	protected function kneadHtml( $html, $type, $pos = 0 ) {

		$doc = new HTML5(
			[
				'disable_html_ns' => true,
			]
		); // Disable default namespace for \DOMXPath compatibility
		$dom = $doc->loadHTML( $html );

		// Download images, change to relative paths
		$dom = $this->scrapeAndKneadImages( $dom );

		// Download audio files, change to relative paths
		$dom = $this->scrapeAndKneadMedia( $dom );

		// Deal with <a href="">, <a href=''>, and other mutations
		$dom = $this->kneadHref( $dom, $type, $pos );

		// Make sure empty tags (e.g. <b></b>) don't get turned into self-closing versions by adding an empty text node to them.
		$xpath = new \DOMXPath( $dom );
		while ( ( $nodes = $xpath->query( '//*[not(text() or node() or self::br or self::hr or self::img)]' ) ) && $nodes->length > 0 ) {
			foreach ( $nodes as $node ) {
				/** @var \DOMElement $node */
				$node->appendChild( new \DOMText( '' ) );
			}
		}

		// Remove srcset attributes because responsive images aren't a thing in the EPUB world.
		$srcsets = $xpath->query( '//img[@srcset]' );
		foreach ( $srcsets as $srcset ) {
			/** @var \DOMElement $srcset */
			$srcset->removeAttribute( 'srcset' );
		}

		// If you are storing multi-byte characters in XML, then saving the XML using saveXML() will create problems.
		// Ie. It will spit out the characters converted in encoded format. Instead do the following:
		$html = $dom->saveXML( $dom->documentElement );

		// Remove auto-created <html> <body> and <!DOCTYPE> tags.
		$html = \Pressbooks\Sanitize\strip_container_tags( $html );

		return $html;
	}

	/**
	 * Nearly verbatim from class-epub201.php in pressbooks 4.4.0
	 * Removed mimetype file requirement
	 * @copyright Pressbooks
	 *
	 * @param $filename
	 *
	 * @return bool
	 */
	protected function zipImscc( $filename ) {
		$zip = new \PclZip( $filename );

		$list = $zip->create( $this->tmpDir . '/imsmanifest.xml', PCLZIP_OPT_NO_COMPRESSION, PCLZIP_OPT_REMOVE_ALL_PATH );
		if ( 0 === absint( $list ) ) {
			return false;
		}

		$files = [];
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->tmpDir ) ) as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			if ( 'imsmanifest.xml' === $file->getFilename() ) {
				continue;
			}
			$files[] = $file->getPathname();
		}

		$list = $zip->add( $files, '', $this->tmpDir );
		if ( 0 === absint( $list ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Mandatory validate method, check the sanity of $this->outputPath
	 *
	 * @return bool
	 */
	function validate() {
		parent::validate();
	}

	/**
	 * Create an imsmanifest.xml file for IMSCC package
	 * Nearly verbatim from `createOPF` in class-epub201.php in pressbooks 4.4.0
	 * Removed `buildManifestAssetHtml()` and changed paths in `file_put_contents()`
	 * @copyright Pressbooks
	 *
	 * @param $metadata
	 *
	 * @throws \Exception
	 */
	protected function createManifest( $metadata ) {

		if ( empty( $this->manifest ) ) {
			throw new \Exception( '$this->manifest cannot be empty. Did you forget to call $this->createWebContentGenerator() ?' );
		}

		// Vars
		$vars = [
			'manifest'   => $this->manifest,
			'stylesheet' => $this->stylesheet,
			'lang'       => $this->lang,
			'images'     => $this->fetchedImageCache,
			'media'      => $this->fetchedMediaCache,
		];

		$vars['do_copyright_license'] = Sanitize\sanitize_xml_attribute(
			wp_strip_all_tags( $this->doCopyrightLicense( $metadata ), true )
		);

		// Sanitize metadata for usage in XML template
		foreach ( $metadata as $key => $val ) {
			$metadata[ $key ] = Sanitize\sanitize_xml_attribute( $val );
		}
		$vars['meta'] = $metadata;

		// Put contents
		file_put_contents(
			$this->tmpDir . '/imsmanifest.xml',
			$this->loadTemplate( $this->dir . '/templates/manifest.php', $vars )
		);

	}


}
