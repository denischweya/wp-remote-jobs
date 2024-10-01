import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit() {
    return (
        <div { ...useBlockProps() }>
            <ServerSideRender
                block="wp-remote-jobs/job-list"
                attributes={ {} }
            />
        </div>
    );
}