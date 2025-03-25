import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import metadata from './block.json';
import save from './save';

// Import styles
import './style.scss';
import './editor.scss';

registerBlockType(metadata.name, {
    icon: {
        src: (
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M20 6H4V4h16v2zm0 2H4v2h16V8zm0 4H4v2h16v-2zm0 4H4v2h16v-2z" />
            </svg>
        )
    },
    
    attributes: {
        layout: {
            type: 'string',
            default: 'grid'
        }
    },

    edit: ({ attributes, setAttributes }) => {
        const { layout } = attributes;

        return (
            <>
                <InspectorControls>
                    <PanelBody title="Layout" initialOpen={true}>
                        <SelectControl
                            label="Display Style"
                            value={layout}
                            options={[
                                { label: 'Grid', value: 'grid' },
                                { label: 'List', value: 'list' }
                            ]}
                            onChange={(newLayout) => setAttributes({ layout: newLayout })}
                        />
                    </PanelBody>
                </InspectorControls>
                <div className={`remjobs-job-list-block is-style-${layout}`}>
                    <div className="job-preview">
                        <h3>Sample Job Title</h3>
                        <div className="job-meta">
                            <span className="company">Sample Company</span>
                            <span className="location">Remote</span>
                        </div>
                    </div>
                </div>
            </>
        );
    },

    save
});