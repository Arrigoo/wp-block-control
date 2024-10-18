import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { ExternalLink, PanelBody, PanelRow, CheckboxControl } from '@wordpress/components';

function addSegmentDisplayControls( BlockEdit ) {

	return ( props ) => {
		const { attributes, setAttributes } = props;
		const segments = window.arrigooCdpSegments || [];

		// Retrieve selected attributes from the block.
		const selectedSegments = attributes.selectedSegments || [];
		
		const handleCheckboxChange = (segment, selectedSegments) => {
			selectedSegments = selectedSegments || [];	
			if (selectedSegments.includes(segment)) {
				const newSelection = selectedSegments.filter((s) => s !== segment);
				console.log('removing segment', segment, newSelection);
				return newSelection;
			}
			const newSelection = [...selectedSegments, segment];
			console.log('adding segment', segment, newSelection);
			return newSelection
		};
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
						<PanelRow>
						{segments.map((segment) => (
							<div key={segment.sys_title}>
							<CheckboxControl
								label={segment.title}
								checked={selectedSegments?.includes(segment.sys_title)}
								onChange={(evt) => {
									console.log(attributes);
									const newAttributes = {...attributes};
									setAttributes(
										{ ...newAttributes, 
											selectedSegments: handleCheckboxChange(segment.sys_title, selectedSegments) 
										});
								}}
							/>
							</div>
						))}
						</PanelRow>
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

const helpText = (
	<>
		{ __(
			"Select the segments from the CDP that are allowed to see the block. If no segments are selected, the block will be visible to everyone.",
			'arrigoo-segment-block-control-script'
		) }
		<ExternalLink
			href={
				'https://www.arrigoo.io/services'
			}
		>
			{ __(
				'Learn more about segments.',
				'arrigoo-segment-block-control'
			) }
		</ExternalLink>
	</>
);

/**
 * Adds a custom 'isDecorative' attribute to all Image blocks.
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
function addSegmentDisplayBlocks( props, blockType, attributes ) {
	const { name } = blockType;
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