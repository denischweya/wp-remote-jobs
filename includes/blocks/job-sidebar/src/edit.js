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
			logo: '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50"><rect width="100%" height="100%" fill="#DDDDDD"/><path fill="#999999" d="m11.74 22.755-.23 1.36q.22-.05.41-.07.2-.02.39-.02.51 0 .9.15.39.16.66.43.26.27.4.63.13.36.13.78 0 .52-.18.94-.18.43-.51.74-.32.3-.76.47-.45.16-.97.16-.3 0-.58-.06-.27-.06-.51-.17t-.45-.24q-.2-.14-.37-.29l.35-.48q.1-.15.28-.15.11 0 .23.07.12.08.27.16.15.09.35.16.21.07.5.07.3 0 .54-.1.23-.1.38-.28.16-.17.24-.42.08-.24.08-.53 0-.53-.31-.83t-.91-.3q-.47 0-.95.17l-.69-.2.54-3.16h3.21v.48q0 .23-.15.38t-.5.15zm8.16 2.25q0 .85-.18 1.47-.19.63-.51 1.04t-.76.61-.95.2q-.5 0-.94-.2-.43-.2-.75-.61t-.5-1.04q-.18-.62-.18-1.47 0-.86.18-1.48.18-.63.5-1.04t.75-.61q.44-.2.94-.2.51 0 .95.2t.76.61.51 1.04q.18.62.18 1.48m-1.15 0q0-.71-.1-1.17-.11-.47-.28-.74-.17-.28-.4-.39-.22-.11-.47-.11-.23 0-.46.11-.22.11-.39.39-.17.27-.27.74-.1.46-.1 1.17 0 .7.1 1.17.1.46.27.73.17.28.39.39.23.11.46.11.25 0 .47-.11.23-.11.4-.39.17-.27.28-.73.1-.47.1-1.17m8.29 1.76-.59.58-1.52-1.52-1.54 1.53-.59-.58 1.54-1.54-1.47-1.47.59-.59 1.46 1.47 1.46-1.46.6.59-1.47 1.46zm4.73-4.01-.23 1.36q.22-.05.42-.07t.38-.02q.51 0 .91.15.39.16.65.43.27.27.4.63.14.36.14.78 0 .52-.18.94-.19.43-.51.74-.32.3-.77.47-.44.16-.96.16-.31 0-.58-.06-.28-.06-.52-.17t-.44-.24q-.21-.14-.37-.29l.34-.48q.11-.15.28-.15.12 0 .23.07.12.08.27.16.15.09.36.16.2.07.49.07.31 0 .54-.1t.39-.28q.15-.17.23-.42.08-.24.08-.53 0-.53-.31-.83-.3-.3-.91-.3-.46 0-.95.17l-.69-.2.54-3.16h3.21v.48q0 .23-.15.38-.14.15-.5.15zm8.16 2.25q0 .85-.18 1.47-.18.63-.5 1.04-.33.41-.76.61-.44.2-.95.2t-.94-.2q-.44-.2-.76-.61t-.5-1.04q-.18-.62-.18-1.47 0-.86.18-1.48.18-.63.5-1.04t.76-.61q.43-.2.94-.2t.95.2q.43.2.76.61.32.41.5 1.04.18.62.18 1.48m-1.15 0q0-.71-.1-1.17-.1-.47-.28-.74-.17-.28-.39-.39-.23-.11-.47-.11t-.46.11q-.23.11-.4.39-.17.27-.27.74-.1.46-.1 1.17 0 .7.1 1.17.1.46.27.73.17.28.4.39.22.11.46.11t.47-.11q.22-.11.39-.39.18-.27.28-.73.1-.47.1-1.17"/></svg>',
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
