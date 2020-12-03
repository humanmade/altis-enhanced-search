<?php
/**
 * Search Configuration Admin Template.
 *
 * @package altis/search
 */

?>
<div class="wrap">
	<h1><?php esc_html_e( 'Search Configuration', 'altis' ); ?></h1>
	<?php if ( $did_update ) : ?>
		<?php if ( empty( $errors ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Search configuration updated!', 'altis' ); ?></p>
				<p><?php esc_html_e( 'Updates can take a few minutes to become active.', 'altis' ); ?></p>
				<?php if ( version_compare( $elasticsearch_version, '7.8', '<' ) ) : ?>
					<p><strong><?php esc_html_e( 'For changes to take effect you must reindex your content once the configuration is showing as active.', 'altis' ); ?></strong></p>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php foreach ( $errors as $error ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $error->get_error_message() ); ?></p>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	<?php endif; ?>
	<p><?php esc_html_e( 'Configure search synonyms here to improve search results.', 'altis' ); ?></p>
	<?php if ( is_network_admin() ) : ?>
		<p class="description"><?php esc_html_e( 'These settings will be used as the default for all sites on the network that match the primary site language.', 'altis' ); ?></p>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'These settings will override the network wide settings if set for this site.', 'altis' ); ?></p>
		<p class="description"><?php esc_html_e( 'Network wide settings are only applied if the primary language matches the language of this site.', 'altis' ); ?></p>
	<?php endif; ?>
	<hr />
	<form action="" method="post" enctype="multipart/form-data">
		<?php if ( get_locale() === 'ja' ) : ?>
			<h2><?php esc_html_e( 'User Dictionary', 'altis' ); ?></h2>
			<div class="columns">
				<div class="column">
					<p><?php esc_html_e( 'A user dictionary provides a way to control how words are broken up. If there are compound words or phrases specific to this site that users may search for they can be specified here to increase search relevancy.', 'altis' ); ?></p>
					<p><?php esc_html_e( 'The syntax for the provided file should be in a CSV format:', 'altis' ); ?></p>
					<pre><?php esc_html_e( 'text, tokens, readings, part-of-speech', 'altis' ); ?></pre>
					<ol>
						<li><?php echo wp_kses( __( '<code>text</code> is the compound word or phrase that appears in your content, such as a name.', 'altis' ), [ 'code' => [] ] ); ?></li>
						<li><?php echo wp_kses( __( '<code>tokens</code> must contain the same text again with spaces added between each word.', 'altis' ), [ 'code' => [] ] ); ?></li>
						<li><?php echo wp_kses( __( '<code>readings</code> must contain the same text as `tokens` with any kanji replaced by katakana. This describes the pronunciation of the tokens.', 'altis' ), [ 'code' => [] ] ); ?></li>
						<li><?php echo wp_kses( __( '<code>part-of-speech</code> defines what the text is, for example a noun or verb.', 'altis' ), [ 'code' => [] ] ); ?></li>
					</ol>
					<p><?php esc_html_e( 'By default the text "東京スカイツリー" would be broken up into "東京", "スカイ" and "ツリ". The example below changes this behavior so that the text is treated as a custom noun:', 'altis' ); ?></p>
					<pre>東京スカイツリー,東京 スカイツリー,トウキョウ スカイツリー,名詞</pre>
					<ol>
						<li><?php echo wp_kses( __( 'The <code>text</code> is "東京スカイツリー"', 'altis' ), [ 'code' => [] ] ); ?></li>
						<li><?php echo wp_kses( __( 'The <code>tokens</code> are "東京" and "スカイツリー"', 'altis' ), [ 'code' => [] ] ); ?></li>
						<li><?php echo wp_kses( __( 'The <code>readings</code> are "トウキョウ" and "スカイツリー"', 'altis' ), [ 'code' => [] ] ); ?></li>
						<li><?php echo wp_kses( __( 'The <code>part-of-speech</code> is "名詞"', 'altis' ), [ 'code' => [] ] ); ?></li>
					</ol>
				</div>
				<div class="column">
					<h3>
						<label for="user-dictionary-file"><?php esc_html_e( 'File upload', 'altis' ); ?></label>
						<?php if ( $types['user_dictionary']['file_date'] && $types['user_dictionary']['uploaded_status'] ) : ?>
							<span class="es-package-status es-package-status--<?php echo esc_attr( strtolower( $types['user_dictionary']['uploaded_status'] ) ); ?>">
								<?php
									// translators: %s replaced by status name e.g. 'ACTIVE', 'ERROR'.
									echo esc_html( sprintf( __( 'Status: %s', 'altis' ), $types['user_dictionary']['uploaded_status'] ) );
								?>
							</span>
						<?php endif; ?>
					</h3>
					<?php if ( $types['user_dictionary']['file_date'] ) : ?>
						<?php if ( $types['user_dictionary']['uploaded_error'] ) : ?>
							<div class="notice notice-error">
								<p><?php echo esc_html( $types['user_dictionary']['uploaded_error'] ); ?></p>
							</div>
						<?php endif; ?>
						<p>
							<?php
							echo esc_html( sprintf(
								// translators: %s is replaced by the file upload date.
								__( 'Current file uploaded on %s', 'altis' ),
								gmdate( get_option( 'date_format', 'Y-m-d H:i:s' ), $types['user_dictionary']['file_date'] )
							) );
							?>
						</p>
						<input type="submit" class="components-button is-link is-destructive" name="user-dictionary-remove" value="<?php esc_attr_e( 'Remove user dictionary file', 'altis' ); ?>" />
					<?php endif; ?>
					<p>
						<input type="file" accept="text/plain" id="user-dictionary-file" name="user-dictionary-file" />
					</p>
					<p>
						<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Update user dictionary', 'altis' ); ?>" />
					</p>
				</div>
			</div>
			<hr />
		<?php endif; ?>
		<h2><?php esc_html_e( 'Synonyms' ); ?></h2>
		<div class="columns">
			<div class="column">
				<p><?php esc_html_e( 'Synonyms allow the search index to treat specific words or phrases as equal. This is useful if:', 'altis' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'you have a lot of acronyms or other contractions in your content', 'altis' ); ?></li>
					<li><?php esc_html_e( 'you have terms in the content that do not appear in the dictionary', 'altis' ); ?></li>
					<li><?php esc_html_e( 'there are common mis-spellings of search terms', 'altis' ); ?></li>
					<li><?php esc_html_e( 'you have users from different countries who may use different words for the same thing', 'altis' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'The syntax for synonyms is as follows:', 'altis' ); ?></p>
				<pre><?php esc_html_e( '# Comments are allowed using a hash at the start the line.', 'altis' ); ?></pre>
				<p><?php esc_html_e( 'Comma separated words or phrases will be treated as equivalent.', 'altis' ); ?></p>
				<pre>sneakers, trainers, footwear, shoes
foozball, foosball, table football
CPU, central processing unit</pre>
				<p><?php esc_html_e( 'Comma separated words or phrases followed by "=>" will be treated the same as comma separated words or phrases to the right of the "=>" operator.', 'altis' ); ?></p>
				<pre>i-pod, i pod => ipod
tent => bivouac, teepee
sea biscuit, sea biscit => seabiscuit</pre>
			</div>
			<div class="column">
				<h3>
					<label for="synonyms-file"><?php esc_html_e( 'File upload', 'altis' ); ?></label>
					<?php if ( $types['synonyms']['file_date'] && $types['synonyms']['uploaded_status'] ) : ?>
						<span class="es-package-status es-package-status--<?php echo esc_attr( strtolower( $types['synonyms']['uploaded_status'] ) ); ?>">
							<?php
								// translators: %s replaced by status name e.g. 'ACTIVE', 'ERROR'.
								echo esc_html( sprintf( __( 'Status: %s', 'altis' ), $types['synonyms']['uploaded_status'] ) );
							?>
						</span>
					<?php endif; ?>
				</h3>
				<p class="description"><?php esc_html_e( 'If you have a large number of synonyms in the order of 1000s a file upload is recommended.' ); ?></p>
				<?php if ( $types['synonyms']['file_date'] ) : ?>
					<?php if ( $types['synonyms']['uploaded_error'] ) : ?>
						<div class="notice notice-error">
							<p><?php echo esc_html( $types['synonyms']['uploaded_error'] ); ?></p>
						</div>
					<?php endif; ?>
					<p>
						<?php
						echo esc_html( sprintf(
							// translators: %s is replaced by the file upload date.
							__( 'Current file uploaded on %s' ),
							gmdate( get_option( 'date_format', 'Y-m-d H:i:s' ), $types['synonyms']['file_date'] )
						) );
						?>
					</p>
					<input type="submit" class="components-button is-link is-destructive" name="synonyms-remove" value="<?php esc_attr_e( 'Remove synonyms file', 'altis' ); ?>" />
				<?php endif; ?>
				<p>
					<input type="file" accept="text/plain" id="synonyms-file" name="synonyms-file" />
				</p>
				<h3>
					<label for="synonyms-text"><?php esc_html_e( 'Manual entry', 'altis' ); ?></label>
					<?php if ( $types['synonyms']['manual_status'] ) : ?>
						<span class="es-package-status es-package-status--<?php echo esc_attr( strtolower( $types['synonyms']['manual_status'] ) ); ?>">
							<?php
								// translators: %s replaced by status name e.g. 'ACTIVE', 'ERROR'.
								echo esc_html( sprintf( __( 'Status: %s', 'altis' ), $types['synonyms']['manual_status'] ) );
							?>
						</span>
					<?php endif; ?>
				</h3>
				<?php if ( $types['synonyms']['manual_error'] ) : ?>
					<div class="notice notice-error">
						<p><?php echo esc_html( $types['synonyms']['manual_error'] ); ?></p>
					</div>
				<?php endif; ?>
				<textarea id="synonyms-text" name="synonyms-text" rows="10" cols="100%"><?php echo esc_textarea( $types['synonyms']['text'] ); ?></textarea>
				<p>
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Update synonyms', 'altis' ); ?>" />
				</p>
			</div>
		</div>
		<hr />
		<h2><?php esc_html_e( 'Stop Words' ); ?></h2>
		<div class="columns">
			<div class="column">
				<p><?php esc_html_e( 'Stop words are words that are ignored when searching. All supported languages have a default list of common stop words by default however you can add additional words here.', 'altis' ); ?></p>
				<p><?php esc_html_e( 'Stop words should be provided one per line for example:', 'altis' ); ?></p>
				<pre>ignore
me
please</pre>
			</div>
			<div class="column">
				<h3>
					<label for="stopwords-file"><?php esc_html_e( 'File upload', 'altis' ); ?></label>
					<?php if ( $types['stopwords']['file_date'] && $types['stopwords']['uploaded_status'] ) : ?>
						<span class="es-package-status es-package-status--<?php echo esc_attr( strtolower( $types['stopwords']['uploaded_status'] ) ); ?>">
							<?php
								// translators: %s replaced by status name e.g. 'ACTIVE', 'ERROR'.
								echo esc_html( sprintf( __( 'Status: %s', 'altis' ), $types['stopwords']['uploaded_status'] ) );
							?>
						</span>
					<?php endif; ?>
				</h3>
				<p class="description"><?php esc_html_e( 'If you have a large number of stop words in the order of 1000s a file upload is recommended.' ); ?></p>
				<?php if ( $types['stopwords']['file_date'] ) : ?>
					<?php if ( $types['stopwords']['uploaded_error'] ) : ?>
						<div class="notice notice-error">
							<p><?php echo esc_html( $types['stopwords']['uploaded_error'] ); ?></p>
						</div>
					<?php endif; ?>
					<p>
						<?php
						echo esc_html( sprintf(
							// translators: %s is replaced by the file upload date.
							__( 'Current file uploaded on %s' ),
							gmdate( get_option( 'date_format', 'Y-m-d H:i:s' ), $types['stopwords']['file_date'] )
						) );
						?>
					</p>
					<input type="submit" class="components-button is-link is-destructive" name="stopwords-remove" value="<?php esc_attr_e( 'Remove stop words file', 'altis' ); ?>" />
				<?php endif; ?>
				<p>
					<input type="file" accept="text/plain" id="stopwords-file" name="stopwords-file" />
				</p>
				<h3>
					<label for="stopwords-text"><?php esc_html_e( 'Manual entry', 'altis' ); ?></label>
					<?php if ( $types['stopwords']['manual_status'] ) : ?>
						<span class="es-package-status es-package-status--<?php echo esc_attr( strtolower( $types['stopwords']['manual_status'] ) ); ?>">
							<?php
								// translators: %s replaced by status name e.g. 'ACTIVE', 'ERROR'.
								echo esc_html( sprintf( __( 'Status: %s', 'altis' ), $types['stopwords']['manual_status'] ) );
							?>
						</span>
					<?php endif; ?>
				</h3>
				<?php if ( $types['stopwords']['manual_error'] ) : ?>
					<div class="notice notice-error">
						<p><?php echo esc_html( $types['stopwords']['manual_error'] ); ?></p>
					</div>
				<?php endif; ?>
				<textarea id="stopwords-text" name="stopwords-text" rows="10" cols="100%"><?php echo esc_textarea( $types['stopwords']['text'] ); ?></textarea>
				<p>
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Update stop words', 'altis' ); ?>" />
				</p>
			</div>
		</div>
		<input type="hidden" name="action" value="altis-search-config" />
		<?php wp_nonce_field( 'altis-search-config', '_altisnonce' ); ?>
	</form>
</div>
