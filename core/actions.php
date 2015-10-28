<?php
/**
 * Add support for MediaPress actions to the myCRED plugin
 */

class MPP_myCRED_Actions_Helper extends myCRED_Hook {
	
	public function __construct ( $hook_prefs, $ptype = 'mycred_default' ) {
		
		//get all active media types
		$types = mpp_get_active_types();
		
		//we are using two array to make sure tha add actions comes before the delete one
		$add_media_args = array();
		$delete_media_args = array();
		
		foreach( $types as $type => $type_object ) {
		
			$add_media_args['add_' . $type ] = array(
				'creds'        => 0,
				'log'          => '%plural% ' . sprintf( __( 'for new %s upload', 'mpp-mycred' ), strtolower( $type_object->singular_name ) ),
				'limit'        => '0/x'
			);
			
			$delete_media_args['delete_' . $type ] = array(
				'creds'        => 0,
				'log'          => '%plural% ' . sprintf( __( 'for %s deletion', 'mpp-mycred' ) , strtolower( $type_object->singular_name ) ),
				'limit'        => '0/x'
			);
			
			
		}
		//now, we have an awrray where add actions are before the delete actions
		$defaults = array_merge( $add_media_args, $delete_media_args );
		
		
		parent::__construct( array(
			'id'       => 'mediapress',
			'defaults' => $defaults,
		), $hook_prefs, $ptype );
		
		
	}
	
	
	public function run() {
		//try adding points when a new media is added
		add_action( 'mpp_media_added',     array( $this, 'new_media' ), 10, 2 );
		add_action( 'mpp_activity_media_marked_attached',     array( $this, 'on_activity_upload' ), 10);
		//try deducting points when a media is deleted
		add_action( 'mpp_delete_media', array( $this, 'delete_media' ) );
		
		
	}
	
	public function new_media( $media_id, $gallery_id = null ) {
		
		$media = mpp_get_media( $media_id );
		
		
		if( ! $media  || $media->is_orphan ) {
			return ;
		}
		
		//activity uploads not yet attached with post should not give any point
				
		//Don't add for excluded users
		if ( $this->core->exclude_user( $media->user_id ) === true ) {
			return;
		}
		//slg: photo|video|audio|docs etc
		$type = $media->type;
		
		// is the typ[e set?
		if ( empty( $type ) ) {
			return ;
		};
		
		//key=> add_photo|add_video etc
		$key = 'add_'. $type;

		// If this media type awards zero, no need to proceed further
		if ( $this->prefs[ $key ] == $this->core->zero() ) {
			return ;
		}
		//reference
		//we are using photo_upload|audio_upload etc
		$ref = 'upload_' . $type;
		
		// Limit
		if ( $this->over_hook_limit( $key, $ref , $media->user_id ) ) {
			return ;
		}
		// Make sure this is unique, I truly don't understand it and will not try to understand
		if ( $this->core->has_entry( $ref, $media->user_id, $media->id ) ) {
			return ;
		}
		
		// Execute
		$this->core->add_creds(
			$ref,
			$media->user_id,
			$this->prefs[ $key ]['creds'],
			$this->prefs[ $key ]['log'],
			$media->id,
			array( 'ref_type' => 'media', 'attachment_id' => $media->id ),
			$this->mycred_type
		);

			
	}
	/**
	 * Give points when activity uploaded media is marked as attached
	 * 
	 * @param type $media_ids
	 */
	public function on_activity_upload( $media_ids ) {
		foreach( $media_ids as $media_id ) {
			$this->new_media( $media_id );
		}
	}
	/**
	 * Runs just before deleting media
	 * 
	 * @param type $media_id
	 * @return type
	 */
	public function delete_media( $media_id ) {
		
		
		$media = mpp_get_media( $media_id );
		
		$type = $media->type;
		
		if ( empty( $type ) ) {
			return ;
		}
		//$key=> 'delete_photo|delete_video etc
		$key = 'delete_' . $type;
		
		// If this media type awards zero, no need to proceed further
		if ( $this->prefs[ $key ] == $this->core->zero() ) { 
			return;
		}

		// Check for user exclusion
		if ( $this->core->exclude_user( $media->user_id ) === true ) {
			return ;
		}
		
		$ref = 'upload_' . $type;
		
		// Only deduct if user gained points for this
		if ( $this->core->has_entry( $ref, $media->user_id, $media_id ) ) {

			// Execute
			$this->core->add_creds(
				$type . '_deletion',
				$media->user_id,
				$this->prefs[ $key ]['creds'],
				$this->prefs[ $key ]['log'],
				$media_id,
				array( 'ref_type' => 'media', 'attachment_id' => $media->id ),
				$this->mycred_type
			);

		}

	}
	

	/**
	 * Preferences for MediaPress (See the myCred->Hooks screen)
	 */
	public function preferences() {
	
		$prefs = $this->prefs;
		?>
		<?php $types = mpp_get_active_types();?>

		<?php foreach( $types as $type => $type_object ) : 

			$key = 'add_' . $type;
			$ref = 'upload_' . $type ;
		?>
			<label class="subheader"><?php printf( __( '<strong>%s</strong> upload', 'mpp-mycred' ), $type_object->singular_name); ?></label>
			<ol>

				<li>
					<label for="<?php echo $this->field_id( array( $key, 'creds' ) ); ?>"><?php  _e( 'Points', 'mpp-mycred' ); ?></label>
					<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $key, 'creds' ) ); ?>" id="<?php echo $this->field_id( array( $key, 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs[ $key ]['creds'] ); ?>" size="8" /></div>
				</li>

				<li class="empty">&nbsp;</li>

				<li>
					<label for="<?php echo $this->field_id( array( $key, 'limit' ) ); ?>"><?php _e( 'Limit', 'mpp-mycred' ); ?></label>
					<?php echo $this->hook_limit_setting( $this->field_name( array( $key, 'limit' ) ), $this->field_id( array( $key, 'limit' ) ), $prefs[ $key ]['limit'] ); ?>
				</li>

				<li>
					<label for="<?php echo $this->field_id( array( $key, 'log' ) ); ?>"><?php _e( 'Log template', 'mpp-mycred' ); ?></label>
					<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $key, 'log' ) ); ?>" id="<?php echo $this->field_id( array( $key, 'log' ) ); ?>" value="<?php echo esc_attr( $prefs[ $key ]['log'] ); ?>" class="long" /></div>
					<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
				</li>


			</ol>
		<?php endforeach;?>
	
	
		<?php foreach( $types as $type => $type_object ) : 
			
			$key = 'delete_' . $type;
			$ref = 'upload_' . $type;
		?>
			<label class="subheader"><?php printf( __( '<strong>%s</strong> delete', 'mpp-mycred' ), $type_object->singular_name); ?></label>
			<ol>

					<li>
						<label for="<?php echo $this->field_id( array( $key, 'creds' ) ); ?>"><?php printf( __( 'Delete %s ', 'mpp-mycred' ), $type_object->singular_name ); ?></label>
						<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $key, 'creds' ) ); ?>" id="<?php echo $this->field_id( array( $key, 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs[ $key ]['creds'] ); ?>" size="8" /></div>
					</li>

					<li>
						<label for="<?php echo $this->field_id( array( $key, 'log' ) ); ?>"><?php _e( 'Log template', 'mpp-mycred' ); ?></label>
						<div class="h2"><input type="text" name="<?php echo $this->field_name( array( $key,  'log' ) ); ?>" id="<?php echo $this->field_id( array( $key,  'log' ) ); ?>" value="<?php echo esc_attr( $prefs[ $key ]['log'] ); ?>" class="long" /></div>
						<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
					</li>
					<li class="empty">&nbsp;</li>


			</ol>
		<?php endforeach;?>
		<?php
	}
	
	
	public function sanitise_preferences( $data ) {

			$types = mpp_get_registered_types();

			foreach( $types as $type => $type_object ) {
				
				$key_delete = 'delete_' . $type;
				$key_add = 'add_' . $type;
				
				if( isset( $data[ $key_add ] ) ) {
					
					$limit = floatval( $data[ $key_add ]['limit'] );
					
					$data[ $key_add ]['limit'] = $limit . '/' . $data[$key_add]['limit_by'];
					unset( $data[ $key_add ]['limit_by'] );
				}
				
				if( isset( $data[ $key_delete ] ) ) {
					
					$limit = floatval( $data[ $key_delete ]['limit'] );
					
					$data[ $key_delete ]['limit'] = $limit . '/' . $data[ $key_delete ]['limit_by'];
					unset( $data[ $key_delete ]['limit_by'] );
				}
				
			}
					
		return $data;
	}
	
	
}
