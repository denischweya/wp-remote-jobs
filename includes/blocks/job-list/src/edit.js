/**
 * WordPress dependencies
 */
import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	SelectControl,
	Placeholder,
	ButtonGroup,
	Button,
	Flex,
	FlexItem,
	Icon,
} from "@wordpress/components";
import { useState } from "@wordpress/element";
import ServerSideRender from "@wordpress/server-side-render";

/**
 * Editor styles
 */
import "./editor.scss";

/**
 * The edit function for the job list block
 */
export default function Edit({ attributes, setAttributes }) {
	const {
		layout,
		jobsPerPage,
		showFilters,
		featuredOnly,
		categories,
		locations,
	} = attributes;

	const blockProps = useBlockProps();

	// Sample preview data
	const [isServerRender, setIsServerRender] = useState(false);

	// Preview job data for editor
	const previewJobs = [
		{
			title: "Senior Product Marketing Manager",
			company: "Descript",
			category: "Marketing & Sales",
			type: "Full Time",
			location: "Ab e Kamari",
			salary: "$250 - $300/week",
			daysLeft: 118,
			premium: true,
		},
		{
			title: "UX/UI Designer",
			company: "Nightfall",
			category: "Design & Creative",
			type: "Remote",
			location: "California",
			salary: "$450 - $900/month",
			daysLeft: 118,
			premium: true,
		},
		{
			title: "Chief of Staff",
			company: "Netomi",
			category: "Development & IT",
			type: "Full Time",
			location: "San Francisco",
			salary: "$25 - $40",
			daysLeft: 118,
			premium: false,
		},
		{
			title: "Junior Product Manager",
			company: "Pulley",
			category: "Product Management",
			type: "Full Time",
			location: "Boston",
			salary: "Negotiable Price",
			daysLeft: 118,
			premium: false,
		},
	];

	// Icons for layout toggle
	const gridIcon = (
		<svg
			viewBox="0 0 24 24"
			width="24"
			height="24"
			stroke="currentColor"
			strokeWidth="2"
			fill="none"
		>
			<rect x="3" y="3" width="7" height="7" rx="1"></rect>
			<rect x="14" y="3" width="7" height="7" rx="1"></rect>
			<rect x="3" y="14" width="7" height="7" rx="1"></rect>
			<rect x="14" y="14" width="7" height="7" rx="1"></rect>
		</svg>
	);

	const listIcon = (
		<svg
			viewBox="0 0 24 24"
			width="24"
			height="24"
			stroke="currentColor"
			strokeWidth="2"
			fill="none"
		>
			<line x1="8" y1="6" x2="21" y2="6"></line>
			<line x1="8" y1="12" x2="21" y2="12"></line>
			<line x1="8" y1="18" x2="21" y2="18"></line>
			<line x1="3" y1="6" x2="3.01" y2="6"></line>
			<line x1="3" y1="12" x2="3.01" y2="12"></line>
			<line x1="3" y1="18" x2="3.01" y2="18"></line>
		</svg>
	);

	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody title={__("Layout Settings", "remote-jobs")}>
					<SelectControl
						label={__("Layout", "remote-jobs")}
						value={layout}
						options={[
							{ label: __("Grid", "remote-jobs"), value: "grid" },
							{ label: __("List", "remote-jobs"), value: "list" },
						]}
						onChange={(value) => setAttributes({ layout: value })}
					/>

					<RangeControl
						label={__("Jobs per page", "remote-jobs")}
						value={jobsPerPage}
						onChange={(value) => setAttributes({ jobsPerPage: value })}
						min={1}
						max={50}
					/>

					<ToggleControl
						label={__("Show filters", "remote-jobs")}
						checked={showFilters}
						onChange={() => setAttributes({ showFilters: !showFilters })}
					/>

					<ToggleControl
						label={__("Featured jobs only", "remote-jobs")}
						checked={featuredOnly}
						onChange={() => setAttributes({ featuredOnly: !featuredOnly })}
					/>
				</PanelBody>
			</InspectorControls>

			{isServerRender ? (
				<ServerSideRender block="remjobs/job-list" attributes={attributes} />
			) : (
				<div className="job-listings-container-editor">
					<div className="job-listings-header">
						<div className="job-count">
							<h2 className="job-count-title">Latest jobs</h2>
							<p className="job-count-stats">
								2020 jobs live - 293 added today.
							</p>
						</div>
						<div className="view-toggle">
							<a href="#" className="view-all-link">
								View all jobs
							</a>
							<div className="layout-toggle">
								<ButtonGroup>
									<Button
										isSmall
										isPrimary={layout === "grid"}
										isSecondary={layout !== "grid"}
										onClick={() => setAttributes({ layout: "grid" })}
										icon={gridIcon}
										aria-label={__("Grid view", "remote-jobs")}
									/>
									<Button
										isSmall
										isPrimary={layout === "list"}
										isSecondary={layout !== "list"}
										onClick={() => setAttributes({ layout: "list" })}
										icon={listIcon}
										aria-label={__("List view", "remote-jobs")}
									/>
								</ButtonGroup>
							</div>
						</div>
					</div>

					<div className={`job-listings-${layout}`}>
						{previewJobs.map((job, index) => {
							const premiumClass = job.premium ? "premium" : "";

							if (layout === "grid") {
								return (
									<div key={index} className={`job-card ${premiumClass}`}>
										<div className="job-card-header">
											<div className="company-logo-container">
												<div
													className="company-logo-placeholder"
													style={{
														backgroundColor: [
															"#4299e1",
															"#48bb78",
															"#f56565",
															"#ed8936",
														][index % 4],
													}}
												>
													{job.company.charAt(0)}
												</div>
											</div>
											<h3 className="job-title">
												<a href="#">{job.title}</a>
											</h3>
											<div className="job-company">
												by <span className="company-name">{job.company}</span>{" "}
												in <span className="job-category">{job.category}</span>
											</div>
										</div>

										<div className="job-card-meta">
											<div className="job-meta-info">
												<div className="job-type-badge">{job.type}</div>
												<div className="job-location">
													<svg
														viewBox="0 0 24 24"
														width="16"
														height="16"
														stroke="currentColor"
														strokeWidth="2"
														fill="none"
													>
														<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
														<circle cx="12" cy="10" r="3"></circle>
													</svg>
													{job.location}
												</div>
												<div className="job-salary">{job.salary}</div>
											</div>
										</div>

										<div className="job-card-footer">
											<span className="days-left">
												{job.daysLeft} days left to apply
											</span>
											<a
												href="#"
												className="save-job-button"
												aria-label="Save job"
											>
												<svg
													viewBox="0 0 24 24"
													width="20"
													height="20"
													stroke="currentColor"
													strokeWidth="2"
													fill="none"
												>
													<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
												</svg>
											</a>
										</div>
									</div>
								);
							} else {
								return (
									<div key={index} className={`job-row ${premiumClass}`}>
										<div className="job-row-main">
											<div className="company-logo-container">
												<div
													className="company-logo-placeholder"
													style={{
														backgroundColor: [
															"#4299e1",
															"#48bb78",
															"#f56565",
															"#ed8936",
														][index % 4],
													}}
												>
													{job.company.charAt(0)}
												</div>
											</div>
											<div className="job-row-content">
												<h3 className="job-title">
													<a href="#">{job.title}</a>
												</h3>
												<div className="job-company">
													by <span className="company-name">{job.company}</span>{" "}
													in{" "}
													<span className="job-category">{job.category}</span>
												</div>
											</div>
										</div>

										<div className="job-row-meta">
											<div className="job-type-badge">{job.type}</div>
											<div className="job-location">
												<svg
													viewBox="0 0 24 24"
													width="16"
													height="16"
													stroke="currentColor"
													strokeWidth="2"
													fill="none"
												>
													<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
													<circle cx="12" cy="10" r="3"></circle>
												</svg>
												{job.location}
											</div>
											<div className="job-salary">{job.salary}</div>
										</div>

										<div className="job-row-actions">
											<span className="days-left">
												{job.daysLeft} days left to apply
											</span>
											<a
												href="#"
												className="save-job-button"
												aria-label="Save job"
											>
												<svg
													viewBox="0 0 24 24"
													width="20"
													height="20"
													stroke="currentColor"
													strokeWidth="2"
													fill="none"
												>
													<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
												</svg>
											</a>
										</div>
									</div>
								);
							}
						})}
					</div>

					<div className="editor-preview-toggle">
						<Button
							variant="secondary"
							onClick={() => setIsServerRender(!isServerRender)}
						>
							{isServerRender
								? __("Show preview", "remote-jobs")
								: __("Show actual content", "remote-jobs")}
						</Button>
					</div>
				</div>
			)}
		</div>
	);
}
