/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit() {
	// Dummy data for editor preview
	const jobData = {
		company: {
			logo: 'https://placehold.co/50x50',
			name: 'Company Name',
			website: 'https://example.com',
		},
		location: {
			remote: true,
			regions: [ 'Africa', 'Europe' ],
		},
		employmentType: 'Internship',
		salaryRange: '$50,000 - $70,000',
		category: 'Marketing',
		skills: [
			'Content Marketing',
			'Non-Tech',
			'Social Media Marketing',
			'Social Proof Tools',
		],
	};

	return (
		<div { ...useBlockProps() } className="job-sidebar">
			{ /* Company Header */ }
			<div className="company-header">
				<img
					src={ jobData.company.logo }
					alt={ jobData.company.name }
					className="company-logo"
				/>
				<h3 className="company-name">{ jobData.company.name }</h3>
			</div>

			{ /* Job Details */ }
			<div className="job-details">
				{ /* Location */ }
				<div className="detail-item">
					<svg
						className="icon"
						viewBox="0 0 24 24"
						fill="none"
						xmlns="http://www.w3.org/2000/svg"
					>
						<path
							d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"
							fill="currentColor"
						/>
					</svg>
					<div>
						<strong>Location</strong>
						<p>
							{ jobData.location.remote ? 'Remote' : '' }{ ' ' }
							{ jobData.location.regions.join( ', ' ) }
						</p>
					</div>
				</div>

				{ /* Employment Type */ }
				<div className="detail-item">
					<svg
						className="icon"
						viewBox="0 0 24 24"
						fill="none"
						xmlns="http://www.w3.org/2000/svg"
					>
						<path
							d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"
							fill="currentColor"
						/>
					</svg>
					<div>
						<strong>Employment Type</strong>
						<p>{ jobData.employmentType }</p>
					</div>
				</div>

				{ /* Salary Range */ }
				<div className="detail-item">
					<svg
						className="icon"
						viewBox="0 0 24 24"
						fill="none"
						xmlns="http://www.w3.org/2000/svg"
					>
						<path
							d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"
							fill="currentColor"
						/>
					</svg>
					<div>
						<strong>Salary Range</strong>
						<p>{ jobData.salaryRange }</p>
					</div>
				</div>

				{ /* Category */ }
				<div className="detail-item">
					<svg
						className="icon"
						viewBox="0 0 24 24"
						fill="none"
						xmlns="http://www.w3.org/2000/svg"
					>
						<path
							d="M12 2l-5.5 9h11L12 2zm0 3.84L13.93 9h-3.87L12 5.84zM17.5 13c-2.49 0-4.5 2.01-4.5 4.5s2.01 4.5 4.5 4.5 4.5-2.01 4.5-4.5-2.01-4.5-4.5-4.5zm0 7c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5zM3 21.5h8v-8H3v8zm2-6h4v4H5v-4z"
							fill="currentColor"
						/>
					</svg>
					<div>
						<strong>Category</strong>
						<p>{ jobData.category }</p>
					</div>
				</div>
			</div>

			{ /* Skills */ }
			<div className="skills-section">
				<strong>Skills</strong>
				<div className="skills-pills">
					{ jobData.skills.map( ( skill, index ) => (
						<span key={ index } className="skill-pill">
							{ skill }
						</span>
					) ) }
				</div>
			</div>

			{ /* Company Website */ }
			<a
				href={ jobData.company.website }
				className="company-website"
				target="_blank"
				rel="noopener noreferrer"
			>
				Visit Company Website
			</a>

			{ /* Apply Button */ }
			<button className="apply-button">Apply Now</button>
		</div>
	);
}
