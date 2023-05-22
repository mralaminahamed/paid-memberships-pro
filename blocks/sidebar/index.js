/**
 * Require Membership sidebar panel.
 */

import apiFetch from '@wordpress/api-fetch';
import { register } from '@wordpress/data';

// Only set up the Require Membership sidebar if we're on a post where we can restrict access.
if ( pmpro_blocks.show_require_membership_panel ) {
	function pmproCustomStore() {
		return {
			name: 'pmpro/require-membership',
			instantiate: () => {
				const listeners = new Set();
				const storeData = { restrictedLevels: [] };

				function storeChanged() {
					for ( const listener of listeners ) {
						listener();
					}
				}

				function subscribe( listener ) {
					listeners.add( listener );
					return () => listeners.delete( listener );
				}

				const selectors = {
					getRestrictedLevels() {
						return storeData['restrictedLevels'];
					},
				};

				const actions = {
					setRestrictedLevels( restrictedLevels ) {
						storeData['restrictedLevels'] = restrictedLevels;
						storeChanged();
					},fetchRestrictedLevels() {
						apiFetch( { path: 'pmpro/v1/post_restrictions/?post_id=' + pmpro_blocks.post_id } )
							.then( ( data ) => {
								// Set the restricted levels to the membership_id values.
								actions.setRestrictedLevels( data.map( ( item ) => item.membership_id ) );
								storeChanged();
							} )
							.catch( ( error ) => {
								console.error( error );
							} );
					},saveRestrictedLevels() {
						apiFetch( {
							path: 'pmpro/v1/post_restrictions/',
							method: 'POST',
							data: {
								post_id: pmpro_blocks.post_id,
								level_ids: storeData['restrictedLevels'],
							},
						} );
					}
				};
				actions.fetchRestrictedLevels();

				return {
					getSelectors: () => selectors,
					getActions: () => actions,
					subscribe,
				};
			},
		};
	}
	register( pmproCustomStore() );

	( function ( wp ) {
		const { __ } = wp.i18n;
		const { registerPlugin } = wp.plugins;
		const { PluginDocumentSettingPanel } = wp.editPost;
		const { Component } = wp.element;
		const { Spinner, CheckboxControl } = wp.components;

		const { withSelect, withDispatch, dispatch } = wp.data;
		const { compose } = wp.compose;
	
		const RequireMembershipControl = compose(
			withDispatch( function ( dispatch, props ) {
				return {
					setRestrictedLevelsValue: function ( value ) {
						dispatch( 'pmpro/require-membership' ).setRestrictedLevels( value );
						// Add another action to update a fake meta value to force the save button to enable.
						dispatch( 'core/editor' ).editPost( { meta: { pmpro_force_save_enable: '1' } } );
					},
				};
			} ),
			withSelect( function ( select, props ) {
				return {
					restrictedLevels: select( 'pmpro/require-membership' ).getRestrictedLevels(),
				};
			} )
		)( function ( props ) {
			const level_checkboxes = props.levels.map(
				( level ) => {
					return (
						<CheckboxControl
							key={ level.id }
							label={ level.name }
							checked={ props.restrictedLevels.includes( level.id ) }
							onChange={ () => {
								let newValue = [...props.restrictedLevels];
								if ( newValue.includes( level.id ) ) {
									newValue = newValue.filter(
										( item ) => item !== level.id
									);
								} else {
									newValue.push( level.id )
								}
								props.setRestrictedLevelsValue( newValue );
							} }
						/>
					)
				}
			);
			return (
				<fragment>
					<h3>{ __('Select levels to restrict by', 'paid-memberships-pro') }</h3>
					{
						level_checkboxes.length > 6 ? (
							<div className="pmpro-scrollable-div">
								{ level_checkboxes }
							</div>
						) : (
							level_checkboxes
						)
					}
				</fragment>
			);
		} );

		// Whenever a post is saved, call the saveRestrictedLevels action.
		wp.data.subscribe( function () {
			if ( wp.data.select( 'core/editor' ).isSavingPost() ) {
				dispatch( 'pmpro/require-membership' ).saveRestrictedLevels();
			}
		} );

		class PMProSidebar extends Component {
			constructor( props ) {
				super( props );
				this.state = {
					levelList: [],
					loadingLevels: true,
				};
			}

			componentDidMount() {
				this.fetchlevels();
			}

			fetchlevels() {
				apiFetch( {
					path: 'pmpro/v1/membership_levels',
				} ).then( ( data ) => {
					// If data is an object, convert to associative array
					if (typeof data === 'object') {
						data = Object.keys(data).map(function(key) {
							return data[key];
						});
					}
					this.setState( {
						levelList: data,
						loadingLevels: false,
					} );
				} ).catch( ( error ) => {
					this.setState( {
						levelList: error,
						loadingLevels: false,
					} );
				} );
			}

			render() {
				var sidebar_content = <Spinner />;
				if ( ! this.state.loadingLevels ) {
					if ( ! Array.isArray( this.state.levelList ) ) {
						sidebar_content = <p>{ __('Error retrieving membership levels.', 'restrict-with-stripe') + ' ' + this.state.levelList }</p>;
					} else if ( this.state.levelList.length === 0 ) {
						sidebar_content = <p>{ __('No levels found. Please create a level to restrict content.', 'paid-memberships-pro') }</p>;
					} else {
						sidebar_content = <div>
							<RequireMembershipControl
								label={ __( 'Membership Levels', 'paid-memberships-pro' ) }
								levels={ this.state.levelList }
							/>
						</div>;
					}
				}

				return (
					<PluginDocumentSettingPanel name="pmpro-sidebar-panel" title={ __( 'Require Membership', 'paid-memberships-pro' ) } >
						{sidebar_content}
					</PluginDocumentSettingPanel>
				);
			}
		}

		registerPlugin( 'pmpro-sidebar', {
			icon: 'lock',
			render: PMProSidebar,
		} );
	} )( window.wp );
}
