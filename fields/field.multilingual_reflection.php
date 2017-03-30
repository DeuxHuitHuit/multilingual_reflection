<?php



	require_once(EXTENSIONS.'/frontend_localisation/lib/class.FLang.php');
	require_once(EXTENSIONS.'/reflectionfield/fields/field.reflection.php');



	Class FieldMultilingual_Reflection extends FieldReflection
	{


		/*-------------------------------------------------------------------------
			Definition:
		-------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();

			$this->_name = __( 'Multilingual Reflection' );

			// Set defaults:
			$this->set( 'show_column', 'yes' );
			$this->set( 'allow_override', 'no' );
			$this->set( 'fetch_associated_counts', 'no' );
			$this->set( 'hide', 'no' );
		}

		public function createTable(){
			$field_id = $this->get( 'id' );

			$query = "
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`entry_id` INT(11) UNSIGNED NOT NULL,
					`handle` VARCHAR(255) DEFAULT NULL,
					`value` TEXT DEFAULT NULL,
					`value_formatted` TEXT DEFAULT NULL,";

			foreach(FLang::getLangs() as $lc)
				$query .= "
					`handle-{$lc}` VARCHAR(255) DEFAULT NULL,
				    `value-{$lc}` TEXT default NULL,
				    `value_formatted-{$lc}` TEXT default NULL,";

			$query .= "
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),";

			foreach(FLang::getLangs() as $lc)
				$query .= "
					KEY `handle-{$lc}` (`handle-{$lc}`),
					FULLTEXT KEY `value-{$lc}` (`value-{$lc}`),
					FULLTEXT KEY `value_formatted-{$lc}` (`value_formatted-{$lc}`),";

			$query .= "
					KEY `handle` (`handle`),
					FULLTEXT KEY `value` (`value`),
					FULLTEXT KEY `value_formatted` (`value_formatted`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			return Symphony::Database()->query( $query );
		}



		/*-------------------------------------------------------------------------
			Publish:
		-------------------------------------------------------------------------*/

		public function displayPublishPanel(&$wrapper, $data = null, $flagWithError = null, $prefix = null, $postfix = null){
			if( $this->get( 'hide' ) == 'yes' ){
				parent::displayPublishPanel( $wrapper, $data, $flagWithError, $prefix, $postfix );
			}
			else{
				Extension_Frontend_Localisation::appendAssets();

				$main_lang = FLang::getMainLang();
				$all_langs = FLang::getAllLangs();
				$langs     = FLang::getLangs();

				$wrapper->setAttribute( 'class', $wrapper->getAttribute( 'class' ).' field-multilingual_reflection field-multilingual' );
				$container = new XMLElement('div', null, array('class' => 'container'));


				/*------------------------------------------------------------------------------------------------*/
				/*  Label  */
				/*------------------------------------------------------------------------------------------------*/

				$label = Widget::Label( $this->get( 'label' ) );
				if( $this->get( 'required' ) != 'yes' ) $label->appendChild( new XMLElement('i', __( 'Optional' )) );
				$container->appendChild( $label );


				/*------------------------------------------------------------------------------------------------*/
				/*  Tabs  */
				/*------------------------------------------------------------------------------------------------*/

				$ul = new XMLElement('ul', null, array('class' => 'tabs'));
				foreach($langs as $lc){
					$li = new XMLElement('li', $all_langs[$lc], array('class' => $lc));
					$lc === $main_lang ? $ul->prependChild( $li ) : $ul->appendChild( $li );
				}

				$container->appendChild( $ul );


				/*------------------------------------------------------------------------------------------------*/
				/*  Panels  */
				/*------------------------------------------------------------------------------------------------*/

				$element_name = $this->get( 'element_name' );

				foreach($langs as $lc){
					$div = new XMLElement('div', null, array('class' => 'tab-panel tab-'.$lc));

					$value = isset($data["value_formatted-$lc"]) ? $data["value_formatted-$lc"] : null;

					$div->appendChild( Widget::Input(
						"fields{$prefix}[$element_name]{$postfix}",
						$value, 'text', array('disabled' => 'disabled')
					) );

					$container->appendChild( $div );
				}


				$wrapper->appendChild( $container );
			}
		}

		/*-------------------------------------------------------------------------
			Input:
		-------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = null){
			$driver = Symphony::ExtensionManager()->create( 'multilingual_reflection' );
			$driver->registerField( $this );

			return self::__OK__;
		}

		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null){
			$status = self::__OK__;

			$result = array(
				'handle'          => null,
				'value'           => null,
				'value_formatted' => null
			);

			foreach(FLang::getLangs() as $lc){
				$result = array_merge( $result, array(
					'handle-'.$lc          => null,
					'value-'.$lc           => null,
					'value_formatted-'.$lc => null
				) );
			}

			return $result;
		}

		/*-------------------------------------------------------------------------
			Output:
		-------------------------------------------------------------------------*/

		public function fetchIncludableElements(){
			$parent_elements     = parent::fetchIncludableElements();
			$includable_elements = $parent_elements;

			$name        = $this->get( 'element_name' );
			$name_length = strlen( $name );

			foreach($parent_elements as $element){
				$includable_elements[] = $name.': all-languages'.substr( $element, $name_length );
			}

			return $includable_elements;
		}

		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode = null){
			// all-languages
			$all_languages = strpos( $mode, 'all-languages' );

			if( $all_languages !== false ){
				$submode = substr( $mode, $all_languages + 15 );

				if( empty($submode) ) $submode = 'formatted';

				$all = new XMLElement($this->get( 'element_name' ), null, array('mode' => $mode));

				foreach(FLang::getLangs() as $lc){
					$data['handle']          = $data['handle-'.$lc];
					$data['value']           = $data['value-'.$lc];
					$data['value_formatted'] = $data['value_formatted-'.$lc];

					$attributes = array(
						'lang' => $lc
					);

					$item = new XMLElement(
						'item', null, $attributes
					);

					parent::appendFormattedElement( $item, $data, $encode, $submode );

					// Reformat generated XML
					$elem = $item->getChild( 0 );
					if( !is_null( $elem ) ){
						$attributes = $elem->getAttributes();
						unset($attributes['mode']);
						$value = $elem->getValue();
						$item->setAttributeArray( $attributes );
						$item->setValue( $value );
						$item->removeChildAt( 0 );
					}

					$all->appendChild( $item );
				}

				$wrapper->appendChild( $all );
			}

			// current-language
			else{
				$lang_code = FLang::getLangCode();

				// If value is empty for this language, load value from main language
				if( $this->get( 'def_ref_lang' ) == 'yes' && $data['value-'.$lang_code] === '' ){
					$lang_code = FLang::getMainLang();
				}


				$data['handle']          = $data['handle-'.$lang_code];
				$data['value']           = $data['value-'.$lang_code];
				$data['value_formatted'] = $data['value_formatted-'.$lang_code];

				parent::appendFormattedElement( $wrapper, $data, $encode, $mode );

				$elem = $wrapper->getChildByName( $this->get( 'element_name' ), 0 );

				if( !is_null( $elem ) ){
					foreach(FLang::getLangs() as $lc){
						$elem->setAttribute( "handle-{$lc}", $data["handle-{$lc}"] );
					}
				}
			}
		}

		public function getParameterPoolValue($data){
			return $data["value-".FLang::getLangCode()];
		}

		public function prepareTableValue($data, XMLElement $link = null){
			$lang_code = Lang::get();

			if( !FLang::validateLangCode( $lang_code ) ){
				$lang_code = FLang::getLangCode();
			}

			// If value is empty for this language, load value from main language
			if( $data['value-'.$lang_code] === '' ){
				__( 'None' );
			}

			$data['value']           = $data['value-'.$lang_code];
			$data['value_formatted'] = $data['value_formatted-'.$lang_code];

			return parent::prepareTableValue( $data, $link );
		}
		
		protected function getLang($data = null) {
			// $required_languages = $this->getRequiredLanguages();
			$lc = Lang::get();

			if (!FLang::validateLangCode($lc)) {
				$lc = FLang::getLangCode();
			}

			// If value is empty for this language, load value from main language
			if (is_array($data) && $this->get('default_main_lang') == 'yes' && empty($data["value-$lc"])) {
				$lc = FLang::getMainLang();
			}

			// If value if still empty try to use the value from the first
			// required language
			// if (is_array($data) && empty($data["value-$lc"]) && count($required_languages) > 0) {
			// 	$lc = $required_languages[0];
			// }

			return $lc;
		}

		public function prepareExportValue($data, $mode, $entry_id = null) {
			$modes = (object)$this->getExportModes();
			$lc = $this->getLang();

			// Export handles:
			if ($mode === $modes->getHandle) {
				if (isset($data["handle-$lc"])) {
					return $data["handle-$lc"];
				}
				else if (isset($data['handle'])) {
					return $data['handle'];
				}
				else if (isset($data["value-$lc"])) {
					return General::createHandle($data["value-$lc"]);
				}
				else if (isset($data['value'])) {
					return General::createHandle($data['value']);
				}
			}

			// Export unformatted:
			else if ($mode === $modes->getValue || $mode === $modes->getPostdata) {
				if (isset($data["value-$lc"])) {
					return $data["value-$lc"];
				}
				return isset($data['value'])
					? $data['value']
					: null;
			}

			// Export formatted:
			else if ($mode === $modes->getFormatted) {
				if (isset($data["value_formatted-$lc"])) {
					return $data["value_formatted-$lc"];
				}
				if (isset($data['value_formatted'])) {
					return $data['value_formatted'];
				}
				else if (isset($data["value-$lc"])) {
					return General::sanitize($data["value-$lc"]);
				}
				else if (isset($data['value'])) {
					return General::sanitize($data['value']);
				}
			}

			return null;
		}


		/*-------------------------------------------------------------------------
			Compile:
		-------------------------------------------------------------------------*/

		public function compile(Entry &$entry){
			$data = array();

			$entry_id   = $entry->get( 'id' );
			$field_id   = $this->get( 'id' );
			$expression = $this->get( 'expression' );

			$crt_lc = Flang::getLangCode();

			foreach(FLang::getLangs() as $lc){
				list($lang, $region) = FLang::extractLanguageBits( $lc );

				FLang::setLangCode( $lang, $region );

				$driver = Symphony::ExtensionManager()->create( 'multilingual_reflection' );
				$xpath  = $driver->getXPath( $entry, $this->get( 'xsltfile' ), $this->get( 'fetch_associated_counts' ) );

				$replacements = array();

				// Find queries:
				preg_match_all( '/\{[^\}]+\}/', $expression, $matches );

				// Find replacements:
				foreach($matches[0] as $match){
					$result = @$xpath->evaluate( 'string('.trim( $match, '{}' ).')' );

					if( !is_null( $result ) ){
						$replacements[$match] = trim( $result );
					}
					else{
						$replacements[$match] = '';
					}
				}

				// Apply replacements:
				$value = str_replace(
					array_keys( $replacements ),
					array_values( $replacements ),
					$expression
				);

				// Apply formatting:
				if( !$value_formatted = $this->applyFormatting( $value ) ){
					$value_formatted = General::sanitize( $value );
				}

				$data = array_merge_recursive( $data, array(
					"handle-$lc"          => Lang::createHandle( $value ),
					"value-$lc"           => $value,
					"value_formatted-$lc" => $value_formatted
				) );
			}

			// restore lang code
			list($lang, $region) = FLang::extractLanguageBits( $crt_lc );

			FLang::setLangCode( $lang, $region );

			// Save:
			Symphony::Database()->update(
				$data,
				"tbl_entries_data_{$field_id}",
				"`entry_id` = '{$entry_id}'"
			);

			$entry->setData( $field_id, $data );
		}

		/*-------------------------------------------------------------------------
			Filtering:
		-------------------------------------------------------------------------*/

		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false){
			$multi_where = '';

			parent::buildDSRetrievalSQL( $data, $joins, $multi_where, $andOperation );

			$lc = FLang::getLangCode();

			$multi_where = str_replace( '.value', ".`value-{$lc}`", $multi_where );
			$multi_where = str_replace( '.handle', ".`handle-{$lc}`", $multi_where );

			$where .= $multi_where;

			return true;
		}

		/*-------------------------------------------------------------------------
			Sorting:
		-------------------------------------------------------------------------*/

		public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC'){
			$field_id = $this->get( 'id' );

			$joins .= "LEFT OUTER JOIN `tbl_entries_data_{$field_id}` AS ed ON (e.id = ed.entry_id) ";

			$lc = FLang::getLangCode();

			if( in_array( strtolower( $order ), array('random', 'rand') ) ){
				$sort = 'ORDER BY RAND()';
			}
			else{
				$sort = sprintf( '
					ORDER BY (
						SELECT `%s`
						FROM tbl_entries_data_%d
						WHERE entry_id = e.id
					) %s',
					'handle-'.$lc,
					$this->get( 'id' ),
					$order
				);
			}
		}

		/*-------------------------------------------------------------------------
			Grouping:
		-------------------------------------------------------------------------*/

		public function groupRecords($records){
			$lc = FLang::getLangCode();

			$groups = array(
				$this->get( 'element_name' ) => array()
			);

			foreach($records as $record){
				$data = $record->getData( $this->get( 'id' ) );

				$handle  = $data['handle-'.$lc];
				$element = $this->get( 'element_name' );

				if( !isset($groups[$element][$handle]) ){
					$groups[$element][$handle] = array(
						'attr'    => array(
							'handle' => $handle
						),
						'records' => array(),
						'groups'  => array()
					);
				}

				$groups[$element][$handle]['records'][] = $record;
			}

			return $groups;
		}

		public function appendFieldSchema($f){
		}
	}

?>
