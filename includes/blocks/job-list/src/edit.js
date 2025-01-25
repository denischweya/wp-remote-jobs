import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function Edit() {
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
		<div {...useBlockProps()}>
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
	);
}
