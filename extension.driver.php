<?php



	require_once EXTENSIONS.'/reflectionfield/extension.driver.php';



	class Extension_Multilingual_Reflection extends Extension_ReflectionField
	{

		const FIELD_TABLE = 'tbl_fields_multilingual_reflection';



		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function uninstall(){
			Symphony::Database()->query( sprintf( "DROP TABLE `%s`", self::FIELD_TABLE ) );
		}

		public function install(){
			Symphony::Database()->query( sprintf(
				"CREATE TABLE IF NOT EXISTS `%s` (
					`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
					`field_id` INT(11) UNSIGNED NOT NULL,
					`xsltfile` VARCHAR(255) DEFAULT NULL,
					`expression` VARCHAR(255) DEFAULT NULL,
					`formatter` VARCHAR(255) DEFAULT NULL,
					`override` ENUM('yes', 'no') DEFAULT 'no',
					`hide` ENUM('yes', 'no') DEFAULT 'no',
					`fetch_associated_counts` ENUM('yes','no') DEFAULT 'no',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
				self::FIELD_TABLE
			) );

			return true;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Delegates  */
		/*------------------------------------------------------------------------------------------------*/

		public function getSubscribedDelegates(){
			return array(
				array(
					'page'     => '/publish/new/',
					'delegate' => 'EntryPostCreate',
					'callback' => 'compileBackendFields'
				),
				array(
					'page'     => '/publish/edit/',
					'delegate' => 'EntryPostEdit',
					'callback' => 'compileFields'
				),
				array(
					'page'     => '/frontend/',
					'delegate' => 'EventPostSaveFilter',
					'callback' => 'compileFields'
				),
				array(
					'page'     => '/extensions/frontend_localisation/',
					'delegate' => 'FLSavePreferences',
					'callback' => 'dFLSavePreferences'
				),
			);
		}

		public function compileFields($context){
			foreach(self::$fields as $field){
				$field->compile( $context['entry'] );
			}
		}

		public function dFLSavePreferences($context){
			$fields = Symphony::Database()->fetch( sprintf( 'SELECT `field_id` FROM `%s`', self::FIELD_TABLE ) );

			if( $fields ){
				// Foreach field check multi language values foreach language
				foreach($fields as $field){
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					try{
						$show_columns = Symphony::Database()->fetch( "SHOW COLUMNS FROM `{$entries_table}` LIKE 'handle-%';" );
					} catch( DatabaseException $dbe ){
						// Field doesn't exist. Better remove it's settings
						Symphony::Database()->query( sprintf(
								"DELETE FROM `%s` WHERE `field_id` = %s;",
								self::FIELD_TABLE, $field["field_id"] )
						);
						continue;
					}

					$columns = array();

					// Remove obsolete fields
					if( $show_columns ){
						foreach($show_columns as $column){
							$lc = substr( $column['Field'], strlen( $column['Field'] ) - 2 );

							// If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
							if( !in_array( $lc, $context['new_langs'] ) ){
								Symphony::Database()->query( sprintf( "
									ALTER TABLE `%s`
										DROP COLUMN `handle-{$lc}`,
										DROP COLUMN `value-{$lc}`,
										DROP COLUMN `value_formatted-{$lc}`;",
									$entries_table ) );
							}
							else{
								$columns[] = $column['Field'];
							}
						}
					}

					// Add new fields
					foreach($context['new_langs'] as $lc){
						// If column lang_code doesn't exist in the language drop columns

						if( !in_array( 'handle-'.$lc, $columns ) ){
							Symphony::Database()->query( sprintf( "
								ALTER TABLE `%s`
									ADD COLUMN `handle-{$lc}` varchar(255) default NULL,
									ADD COLUMN `value-{$lc}` int(11) unsigned NULL,
									ADD COLUMN `value_formatted-{$lc}` varchar(255) default NULL;",
								$entries_table ) );
						}
					}

				}
			}
		}

	}

?>
