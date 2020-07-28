<?php
/**
 * Search Configuration Admin Template.
 *
 * @package altis/search
 */

?>
<style>
	.columns { display: flex; flex-wrap: wrap; }
	.column { flex: 1 1 300px; width: 300px; margin-right: 20px; }
	.column textarea { width: 100%; }
	.column pre { background: #fff; border: 1px solid #152a4e; padding: 10px; overflow: auto; }
	.column ul li { margin-left: 20px; list-style: disc; }
	.es-package-status { float: right; text-transform: uppercase; padding: 3px; font-size: 70%; border-radius: 3px; background: #152a4e; color: #fff; }
	.es-package-status--active { background-color: green; }
</style>
<div class="wrap">
	<h1><?php esc_html_e( 'Search Configuration', 'altis' ); ?></h1>
	<?php if ( $did_update ) : ?>
		<?php if ( empty( $errors ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Search configuration updated!', 'altis' ); ?></p>
				<p><?php esc_html_e( 'Updates can take a few moments to propagate fully.', 'altis' ); ?></p>
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
					<pre><?php esc_html_e( 'text, token 1 ... token n, reading 1 ... reading n, part-of-speech tag', 'altis' ); ?></pre>
					<p><?php esc_html_e( 'For example', 'altis' ); ?>:</p>
					<pre>東京スカイツリー,東京 スカイツリー,トウキョウ スカイツリー,カスタム名詞</pre>
				</div>
				<div class="column">
					<h3>
						<label for="user-dictionary-file"><?php esc_html_e( 'File upload', 'altis' ); ?></label>
						<?php if ( $user_dictionary_file_date && $user_dictionary_uploaded_status ) : ?>
							<span class="es-package-status es-package-status--<?php echo esc_attr( $user_dictionary_uploaded_status ); ?>">
								<?php esc_html__( 'Status', 'altis' ); ?>: <?php echo esc_html( $user_dictionary_uploaded_status ); ?>
							</span>
						<?php endif; ?>
					</h3>
					<?php if ( $user_dictionary_file_date ) : ?>
						<?php if ( $user_dictionary_uploaded_error ) : ?>
							<div class="notice notice-error">
								<p><?php echo esc_html( $user_dictionary_uploaded_error ); ?></p>
							</div>
						<?php endif; ?>
						<p>
							<?php
							echo esc_html( sprintf(
								// translators: %s is replaced by the file upload date.
								__( 'Current file uploaded on %s', 'altis' ),
								gmdate( get_option( 'date_format', 'Y-m-d H:i:s' ), $user_dictionary_file_date )
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
				<pre># <?php esc_html_e( 'Comments are allowed using a hash at the start the line.', 'altis' ); ?></pre>
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
					<?php if ( $synonyms_file_date && $synonyms_uploaded_status ) : ?>
						<span class="es-package-status es-package-status--<?php echo esc_attr( $synonyms_uploaded_status ); ?>">
							<?php esc_html__( 'Status', 'altis' ); ?>: <?php echo esc_html( $synonyms_uploaded_status ); ?>
						</span>
					<?php endif; ?>
				</h3>
				<p class="description"><?php esc_html_e( 'If you have a large number of synonyms in the order of 1000s a file upload is recommended.' ); ?></p>
				<?php if ( $synonyms_file_date ) : ?>
					<?php if ( $synonyms_uploaded_error ) : ?>
						<div class="notice notice-error">
							<p><?php echo esc_html( $synonyms_uploaded_error ); ?></p>
						</div>
					<?php endif; ?>
					<p>
						<?php
						echo esc_html( sprintf(
							// translators: %s is replaced by the file upload date.
							__( 'Current file uploaded on %s' ),
							gmdate( get_option( 'date_format', 'Y-m-d H:i:s' ), $synonyms_file_date )
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
					<?php if ( $synonyms_manual_status ) : ?>
						<span class="es-package-status es-package-status--<?php echo esc_attr( $synonyms_manual_status ); ?>">
							<?php esc_html__( 'Status', 'altis' ); ?>: <?php echo esc_html( $synonyms_manual_status ); ?>
						</span>
					<?php endif; ?>
				</h3>
				<?php if ( $synonyms_manual_error ) : ?>
					<div class="notice notice-error">
						<p><?php echo esc_html( $synonyms_manual_error ); ?></p>
					</div>
				<?php endif; ?>
				<textarea id="synonyms-text" name="synonyms-text" rows="10" cols="100%"><?php echo esc_textarea( $synonyms_text ); ?></textarea>
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
					<?php if ( $stopwords_file_date && $stopwords_uploaded_status ) : ?>
						<span class="es-package-status es-package-status--<?php echo esc_attr( $stopwords_uploaded_status ); ?>">
							<?php esc_html__( 'Status', 'altis' ); ?>: <?php echo esc_html( $stopwords_uploaded_status ); ?>
						</span>
					<?php endif; ?>
				</h3>
				<p class="description"><?php esc_html_e( 'If you have a large number of stop words in the order of 1000s a file upload is recommended.' ); ?></p>
				<?php if ( $stopwords_file_date ) : ?>
					<?php if ( $stopwords_uploaded_error ) : ?>
						<div class="notice notice-error">
							<p><?php echo esc_html( $stopwords_uploaded_error ); ?></p>
						</div>
					<?php endif; ?>
					<p>
						<?php
						echo esc_html( sprintf(
							// translators: %s is replaced by the file upload date.
							__( 'Current file uploaded on %s' ),
							gmdate( get_option( 'date_format', 'Y-m-d H:i:s' ), $stopwords_file_date )
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
					<?php if ( $stopwords_manual_status ) : ?>
						<span class="es-package-status es-package-status--<?php echo esc_attr( $stopwords_manual_status ); ?>">
							<?php esc_html__( 'Status', 'altis' ); ?>: <?php echo esc_html( $stopwords_manual_status ); ?>
						</span>
					<?php endif; ?>
				</h3>
				<?php if ( $stopwords_manual_error ) : ?>
					<div class="notice notice-error">
						<p><?php echo esc_html( $stopwords_manual_error ); ?></p>
					</div>
				<?php endif; ?>
				<textarea id="stopwords-text" name="stopwords-text" rows="10" cols="100%"><?php echo esc_textarea( $stopwords_text ); ?></textarea>
				<p>
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Update stop words', 'altis' ); ?>" />
				</p>
			</div>
		</div>
		<input type="hidden" name="action" value="altis-search-config" />
		<?php wp_nonce_field( 'altis-search-config', '_altisnonce' ); ?>
	</form>
</div>
