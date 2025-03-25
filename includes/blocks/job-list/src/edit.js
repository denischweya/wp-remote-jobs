import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function Edit({ attributes, setAttributes }) {
	const { layout = 'grid' } = attributes;
	const [jobs, setJobs] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState(null);

	useEffect(() => {
		fetchJobs();
	}, []);

	const fetchJobs = async () => {
		try {
			const response = await apiFetch({
				path: '/wp-remote-jobs/v1/jobs',
			});
			setJobs(response);
			setError(null);
		} catch (err) {
			setError(err.message);
		} finally {
			setIsLoading(false);
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Layout Settings', 'remote-jobs')} initialOpen={true}>
					<SelectControl
						label={__('Display Style', 'remote-jobs')}
						value={layout}
						options={[
							{ label: __('Grid', 'remote-jobs'), value: 'grid' },
							{ label: __('List', 'remote-jobs'), value: 'list' }
						]}
						onChange={(newLayout) => setAttributes({ layout: newLayout })}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...useBlockProps({ className: `is-style-${layout}` })}>
				{isLoading && <p>{__('Loading jobs...', 'remote-jobs')}</p>}
				{error && <p className="error">{error}</p>}
				{!isLoading && !error && (
					<div className="job-list">
						{jobs.map((job) => (
							<div key={job.id} className="job-item">
								<h3>{job.title}</h3>
								<div className="job-meta">
									<span>{job.location.join(', ')}</span>
									<span>{job.categories.join(', ')}</span>
								</div>
								<div className="job-excerpt">{job.excerpt}</div>
							</div>
						))}
					</div>
				)}
			</div>
		</>
	);
}
