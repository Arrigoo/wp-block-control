import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, FormTokenField, Tooltip } from '@wordpress/components';

function addSegmentDisplayControls( BlockEdit ) {

	return ( props ) => {
		const { attributes, setAttributes } = props;
		const segments = window.arrigooCdpSegments || [];

		// Retrieve selected attributes from the block.
		const selectedSegments = attributes.selectedSegments || [];

		// Create a map of sys_title to title for display purposes
		const segmentMap = segments.reduce((acc, segment) => {
			acc[segment.sys_title] = segment.title;
			return acc;
		}, {});

		// Add 'unknown' option to the segment map
		segmentMap['unknown'] = 'Unknown user';

		// Get available segment titles for suggestions, including 'Unknown user'
		const availableSegmentTitles = [
			'Unknown user',
			...segments.map(segment => segment.title)
		];

		// Split selectedSegments into show (without !) and hide (with !)
		const showSegments = selectedSegments.filter(seg => !seg.startsWith('!'));
		const hideSegments = selectedSegments
			.filter(seg => seg.startsWith('!'))
			.map(seg => seg.substring(1)); // Remove the '!' prefix for display

		// Convert sys_titles to display titles for the "Show to segments" field
		const showTitles = showSegments
			.map(sysTitle => segmentMap[sysTitle])
			.filter(Boolean);

		// Convert sys_titles to display titles for the "Hide from segments" field
		const hideTitles = hideSegments
			.map(sysTitle => segmentMap[sysTitle])
			.filter(Boolean);

		const handleShowSegmentChange = (selectedTitles) => {
			// Convert display titles back to sys_titles
			const newShowSysTitles = selectedTitles
				.map(title => {
					// Handle 'Unknown user' option
					if (title === 'Unknown user') {
						return 'unknown';
					}
					const segment = segments.find(s => s.title === title);
					return segment ? segment.sys_title : null;
				})
				.filter(Boolean);

			// Get hide segments, but remove any that are now in show
			const hideWithPrefix = selectedSegments
				.filter(seg => seg.startsWith('!'))
				.filter(seg => !newShowSysTitles.includes(seg.substring(1)));

			setAttributes({ selectedSegments: [...newShowSysTitles, ...hideWithPrefix] });
		};

		const handleHideSegmentChange = (selectedTitles) => {
			// Convert display titles back to sys_titles and prefix with '!'
			const newHideSysTitles = selectedTitles
				.map(title => {
					// Handle 'Unknown user' option
					if (title === 'Unknown user') {
						return '!unknown';
					}
					const segment = segments.find(s => s.title === title);
					return segment ? '!' + segment.sys_title : null;
				})
				.filter(Boolean);

			// Get the sys_titles without the '!' prefix for comparison
			const newHideSysTitlesWithoutPrefix = newHideSysTitles.map(seg => seg.substring(1));

			// Get show segments, but remove any that are now in hide
			const showWithoutPrefix = selectedSegments
				.filter(seg => !seg.startsWith('!'))
				.filter(seg => !newHideSysTitlesWithoutPrefix.includes(seg));

			setAttributes({ selectedSegments: [...showWithoutPrefix, ...newHideSysTitles] });
		};

		// Create segment list for tooltip - each on a new line
		const allSegmentTitles = ['Unknown user', ...segments.map(segment => segment.title)];
		const segmentListItems = allSegmentTitles.length > 0
			? allSegmentTitles
			: [__('No segments available', 'arrigoo-segment-block-control-script')];

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __(
							'CDP Segment control',
							'arrigoo-segment-block-control-script'
						) }
					>
						<div style={{ marginBottom: '12px' }}>
							<Tooltip
								text={
									<div style={{ textAlign: 'left' }}>
										<strong>{ __('Available segments:', 'arrigoo-segment-block-control-script') }</strong>
										<div style={{ marginTop: '8px' }}>
											{ segmentListItems.map((segment, index) => (
												<div key={index} style={{ marginBottom: '4px' }}>
													{ segment }
												</div>
											)) }
										</div>
									</div>
								}
								placement="top"
							>
								<span style={{
									display: 'inline-flex',
									alignItems: 'center',
									gap: '6px',
									cursor: 'help',
									color: '#2271b1',
									fontSize: '12px',
									padding: '4px 0'
								}}>
									<svg
										width="16"
										height="16"
										viewBox="0 0 24 24"
										fill="currentColor"
										xmlns="http://www.w3.org/2000/svg"
									>
										<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
									</svg>
									{ __('Show available segments', 'arrigoo-segment-block-control-script') }
								</span>
							</Tooltip>
						</div>
						<FormTokenField
							label={ __(
								'Only show to segments',
								'arrigoo-segment-block-control-script'
							) }
							value={ showTitles }
							suggestions={ availableSegmentTitles }
							onChange={ handleShowSegmentChange }
							placeholder={ __(
								'Type to search segments...',
								'arrigoo-segment-block-control-script'
							) }
							__experimentalShowHowTo={ false }
						/>
						<FormTokenField
							label={ __(
								'Hide from segments',
								'arrigoo-segment-block-control-script'
							) }
							value={ hideTitles }
							suggestions={ availableSegmentTitles }
							onChange={ handleHideSegmentChange }
							placeholder={ __(
								'Type to search segments...',
								'arrigoo-segment-block-control-script'
							) }
							__experimentalShowHowTo={ false }
						/>
						<p style={{ marginTop: '12px', fontSize: '12px', color: '#757575' }}>
							{ __(
								'If both are empty, the block is visible to everyone.',
								'arrigoo-segment-block-control-script'
							) }
						</p>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}

addFilter(
	'editor.BlockEdit',
	'arrigoo-cdp/arrigoo-segment-block-control-script',
	addSegmentDisplayControls
);

/**
 * Adds selectedSegments attribute to all blocks.
 *
 * @param {Object} settings The block settings for the registered block type.
 * @param {string} name     The block type name, including namespace.
 * @return {Object}         The modified block settings.
 */
function addCDPSegmentsAttribute( settings, name ) {

	settings.attributes = {
		...settings.attributes,
		selectedSegments: {
			type: 'array',
			default: [],
		},
	};

	return settings;
}

addFilter(
	'blocks.registerBlockType',
	'arrigoo-cdp/add-cdp-segments-attribute',
	addCDPSegmentsAttribute
);

/**
 * Adds the role attribute to the root element in decorative Image blocks.
 *
 * @param {Object} props       The current `save` element’s props to be modified and returned.
 * @param {Object} blockType   The block type definition object.
 * @param {Object} attributes  The block's attributes.
 * @return {Object}            The modified properties with the `role` attribute added, or the original properties if conditions are not met.
 */
function addSegmentDisplayBlocks( props, attributes ) {
	const { selectedSegments } = attributes;

	if ( selectedSegments && selectedSegments.length > 0 ) {
		const classes = props.class || [];
		classes.push( 'arrigoo-segment-block' );

		return {
			...props,
			'data-segments': selectedSegments.join( ' ' ),
			class: classes,
		};
	}

	return props;
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'arrigoo-cdp/arrigoo-segment-block-control-filter',
	addSegmentDisplayBlocks
);

/**
 * Add segment-specific classes to blocks in the editor.
 *
 * @param {Function} BlockListBlock The original BlockListBlock component.
 * @return {Function} The wrapped BlockListBlock component with segment classes.
 */
function addSegmentClassInEditor( BlockListBlock ) {
	return ( props ) => {
		const { attributes } = props;
		const { selectedSegments } = attributes;

		if ( selectedSegments && selectedSegments.length > 0 ) {
			const segmentClasses = selectedSegments.map( segment => `segment-${segment}` ).join( ' ' );
			return (
				<BlockListBlock
					{ ...props }
					className={ segmentClasses }
				/>
			);
		}

		return <BlockListBlock { ...props } />;
	};
}

addFilter(
	'editor.BlockListBlock',
	'arrigoo-cdp/add-segment-class-in-editor',
	addSegmentClassInEditor
);
