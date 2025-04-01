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
	FormTokenField,
	TextControl,
	CheckboxControl,
	Notice,
	Panel,
	Spinner,
} from "@wordpress/components";
import { useState, useEffect } from "@wordpress/element";
import ServerSideRender from "@wordpress/server-side-render";
import apiFetch from "@wordpress/api-fetch";

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
		postsPerPage,
		showFilters,
		showToggle,
		featuredOnly,
		showCategoryFilter,
		showLocationFilter,
		showSkillsFilter,
		showSearchFilter,
		filterByCategory,
		filterBySkills,
		filterByLocation,
		viewAllJobsPage,
	} = attributes;

	const blockProps = useBlockProps();

	// State for taxonomy terms and pages
	const [availableCategories, setAvailableCategories] = useState([]);
	const [availableSkills, setAvailableSkills] = useState([]);
	const [availableLocations, setAvailableLocations] = useState([]);
	const [availablePages, setAvailablePages] = useState([]);
	const [isLoadingTerms, setIsLoadingTerms] = useState(false);
	const [isLoadingPages, setIsLoadingPages] = useState(false);
	const [hasJobs, setHasJobs] = useState(false);
	const [isCheckingJobs, setIsCheckingJobs] = useState(true);
	const [showDebug, setShowDebug] = useState(false);

	// State for server render vs preview - start with actual content
	const [isServerRender, setIsServerRender] = useState(true);
	const [isServerLoading, setIsServerLoading] = useState(true);

	// Fetch available pages
	useEffect(() => {
		const fetchPages = async () => {
			setIsLoadingPages(true);
			try {
				const response = await apiFetch({
					path: '/wp/v2/pages?per_page=100',
				});
				setAvailablePages(
					response.map((page) => ({
						value: page.id,
						label: page.title.rendered,
					}))
				);
			} catch (error) {
				console.error('Error fetching pages:', error);
			} finally {
				setIsLoadingPages(false);
			}
		};

		fetchPages();
	}, []);

	// Check if there are any job posts
	useEffect(() => {
		const checkJobPosts = async () => {
			setIsCheckingJobs(true);
			try {
				const response = await apiFetch({
					path: "/wp/v2/jobs?per_page=1",
				});
				setHasJobs(response && response.length > 0);
			} catch (error) {
				console.error("Error checking for job posts:", error);
				setHasJobs(false);
			} finally {
				setIsCheckingJobs(false);
			}
		};

		checkJobPosts();
	}, []);

	// Fetch taxonomy terms
	useEffect(() => {
		const fetchTerms = async () => {
			setIsLoadingTerms(true);
			try {
				// Fetch categories
				const categoriesResponse = await apiFetch({
					path: "/wp/v2/job_category?per_page=100",
				});
				setAvailableCategories(
					categoriesResponse.map((term) => ({
						id: term.id,
						name: term.name,
						slug: term.slug,
					}))
				);

				// Fetch skills
				const skillsResponse = await apiFetch({
					path: "/wp/v2/job_skills?per_page=100",
				});
				setAvailableSkills(
					skillsResponse.map((term) => ({
						id: term.id,
						name: term.name,
						slug: term.slug,
					}))
				);

				// Fetch locations
				const locationsResponse = await apiFetch({
					path: "/wp/v2/job_location?per_page=100",
				});
				setAvailableLocations(
					locationsResponse.map((term) => ({
						id: term.id,
						name: term.name,
						slug: term.slug,
					}))
				);
			} catch (error) {
				console.error("Error fetching terms:", error);
			} finally {
				setIsLoadingTerms(false);
			}
		};

		fetchTerms();
	}, []);

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

	// Helper functions for taxonomy term handling
	const handleCategoryChange = (selectedCategories) => {
		const categorySlugs = selectedCategories.map((category) => {
			const found = availableCategories.find((c) => c.name === category);
			return found ? found.slug : category;
		});
		setAttributes({ filterByCategory: categorySlugs });
	};

	const handleSkillsChange = (selectedSkills) => {
		const skillSlugs = selectedSkills.map((skill) => {
			const found = availableSkills.find((s) => s.name === skill);
			return found ? found.slug : skill;
		});
		setAttributes({ filterBySkills: skillSlugs });
	};

	const handleLocationChange = (selectedLocations) => {
		const locationSlugs = selectedLocations.map((location) => {
			const found = availableLocations.find((l) => l.name === location);
			return found ? found.slug : location;
		});
		setAttributes({ filterByLocation: locationSlugs });
	};

	// Handle server side render loading state
	const handleServerRenderLoadingState = (status) => {
		setIsServerLoading(status === 'loading');
	};

	// Render all inspector controls
	const renderInspectorControls = () => {
		return (
			<>
				<PanelBody title={__("Layout Settings", "remote-jobs")} initialOpen={true}>
					<SelectControl
						label={__("Display Style", "remote-jobs")}
						value={layout}
						options={[
							{ label: __("Grid", "remote-jobs"), value: "grid" },
							{ label: __("List", "remote-jobs"), value: "list" },
						]}
						onChange={(value) => setAttributes({ layout: value })}
					/>

					<ToggleControl
						label={__("Show layout toggle buttons", "remote-jobs")}
						help={
							showToggle
								? __("Layout toggle buttons are visible", "remote-jobs")
								: __("Layout toggle buttons are hidden", "remote-jobs")
						}
						checked={showToggle}
						onChange={() => setAttributes({ showToggle: !showToggle })}
					/>

					<RangeControl
						label={__("Jobs per page", "remote-jobs")}
						value={postsPerPage || 10}
						onChange={(value) => setAttributes({ postsPerPage: value })}
						min={1}
						max={50}
						help={__("Number of jobs to display per page", "remote-jobs")}
					/>

					<SelectControl
						label={__("View All Jobs Page", "remote-jobs")}
						value={viewAllJobsPage}
						options={[
							{ label: __("Select a page...", "remote-jobs"), value: 0 },
							...availablePages
						]}
						onChange={(value) => setAttributes({ viewAllJobsPage: parseInt(value) })}
						help={__("Select the page where all jobs will be displayed", "remote-jobs")}
					/>

					<ToggleControl
						label={__("Show filters", "remote-jobs")}
						checked={showFilters}
						onChange={() => setAttributes({ showFilters: !showFilters })}
						help={__("Display filter controls above job listings", "remote-jobs")}
					/>

					<ToggleControl
						label={__("Featured jobs only", "remote-jobs")}
						checked={featuredOnly}
						onChange={() => setAttributes({ featuredOnly: !featuredOnly })}
						help={__("Only display featured/premium job listings", "remote-jobs")}
					/>
				</PanelBody>

				<PanelBody title={__("Filter Controls", "remote-jobs")} initialOpen={false}>
					<p className="components-base-control__help">
						{__("Choose which filter controls to display", "remote-jobs")}
					</p>

					<ToggleControl
						label={__("Show search box", "remote-jobs")}
						checked={showSearchFilter}
						onChange={() => setAttributes({ showSearchFilter: !showSearchFilter })}
						help={__("Allow users to search for jobs by keyword", "remote-jobs")}
					/>

					<ToggleControl
						label={__("Show category filter", "remote-jobs")}
						checked={showCategoryFilter}
						onChange={() => setAttributes({ showCategoryFilter: !showCategoryFilter })}
						help={__("Allow users to filter jobs by category", "remote-jobs")}
					/>

					<ToggleControl
						label={__("Show location filter", "remote-jobs")}
						checked={showLocationFilter}
						onChange={() => setAttributes({ showLocationFilter: !showLocationFilter })}
						help={__("Allow users to filter jobs by location", "remote-jobs")}
					/>

					<ToggleControl
						label={__("Show skills filter", "remote-jobs")}
						checked={showSkillsFilter}
						onChange={() => setAttributes({ showSkillsFilter: !showSkillsFilter })}
						help={__("Allow users to filter jobs by required skills", "remote-jobs")}
					/>
				</PanelBody>

				<PanelBody title={__("Filter Settings", "remote-jobs")} initialOpen={false}>
					<p className="components-base-control__help">
						{__("Select specific categories, skills, or locations to filter jobs", "remote-jobs")}
					</p>

					{isLoadingTerms ? (
						<p>{__("Loading taxonomy terms...", "remote-jobs")}</p>
					) : (
						<>
							<FormTokenField
								label={__("Filter by Categories", "remote-jobs")}
								value={filterByCategory ? filterByCategory.map((slug) => {
									const found = availableCategories.find((c) => c.slug === slug);
									return found ? found.name : slug;
								}) : []}
								suggestions={availableCategories.map((cat) => cat.name)}
								onChange={handleCategoryChange}
								help={__("Type to select job categories to display", "remote-jobs")}
							/>

							<FormTokenField
								label={__("Filter by Skills", "remote-jobs")}
								value={filterBySkills ? filterBySkills.map((slug) => {
									const found = availableSkills.find((s) => s.slug === slug);
									return found ? found.name : slug;
								}) : []}
								suggestions={availableSkills.map((skill) => skill.name)}
								onChange={handleSkillsChange}
								help={__("Type to select job skills to display", "remote-jobs")}
							/>

							<FormTokenField
								label={__("Filter by Locations", "remote-jobs")}
								value={filterByLocation ? filterByLocation.map((slug) => {
									const found = availableLocations.find((l) => l.slug === slug);
									return found ? found.name : slug;
								}) : []}
								suggestions={availableLocations.map((loc) => loc.name)}
								onChange={handleLocationChange}
								help={__("Type to select job locations to display", "remote-jobs")}
							/>
						</>
					)}
				</PanelBody>

				<PanelBody title={__("Debug Info", "remote-jobs")} initialOpen={false}>
					<ToggleControl
						label={__("Show debug info", "remote-jobs")}
						checked={showDebug}
						onChange={() => setShowDebug(!showDebug)}
					/>
					{showDebug && (
						<div style={{ fontSize: "12px", wordBreak: "break-word" }}>
							<h4>Current Attributes:</h4>
							<pre>{JSON.stringify(attributes, null, 2)}</pre>
						</div>
					)}
				</PanelBody>
			</>
		);
	};

	return (
		<div {...blockProps}>
			<InspectorControls>
				{renderInspectorControls()}
			</InspectorControls>

			{isServerRender ? (
				<>
					<ServerSideRender 
						block="remjobs/job-list" 
						attributes={attributes} 
						EmptyResponsePlaceholder={() => (
							<Placeholder label={__("No Jobs Found", "remote-jobs")}>
								<p>{__("There are no jobs that match your criteria.", "remote-jobs")}</p>
							</Placeholder>
						)}
						LoadingResponsePlaceholder={() => (
							<Placeholder label={__("Loading Jobs...", "remote-jobs")}>
								<Spinner />
							</Placeholder>
						)}
						onFetchStateChange={handleServerRenderLoadingState}
					/>
					<div className="editor-preview-toggle">
						<Button
							variant="secondary"
							onClick={() => setIsServerRender(!isServerRender)}
						>
							{__("Show editor preview", "remote-jobs")}
						</Button>
					</div>
				</>
			) : (
				<div className="job-listings-container-editor">
					{isCheckingJobs ? (
						<Placeholder label={__("Loading...", "remote-jobs")} />
					) : !hasJobs ? (
						<div className="no-jobs-found">
							<p>{__("No jobs have been added yet.", "remote-jobs")}</p>
						</div>
					) : (
						<>
							<div className="job-listings-header">
								<div className="job-count">
									<h2 className="job-count-title">Latest jobs</h2>
									<p className="job-count-stats">
										{__("Jobs will be displayed here", "remote-jobs")}
									</p>
								</div>
								{showToggle && (
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
								)}
							</div>

							{showFilters && (
								<div className="job-filters">
									<div className="filter-row">
										{showSearchFilter && (
											<input
												type="text"
												placeholder={__("Search jobs...", "remote-jobs")}
												className="search-input"
											/>
										)}

										{showCategoryFilter && availableCategories.length > 0 && (
											<select className="category-select">
												<option value="">
													{__("All Categories", "remote-jobs")}
												</option>
												{availableCategories.map((cat) => (
													<option key={cat.id} value={cat.slug}>
														{cat.name}
													</option>
												))}
											</select>
										)}

										{showLocationFilter && availableLocations.length > 0 && (
											<select className="location-select">
												<option value="">{__("All Locations", "remote-jobs")}</option>
												{availableLocations.map((loc) => (
													<option key={loc.id} value={loc.slug}>
														{loc.name}
													</option>
												))}
											</select>
										)}

										{showSkillsFilter && availableSkills.length > 0 && (
											<select className="skills-select">
												<option value="">{__("All Skills", "remote-jobs")}</option>
												{availableSkills.map((skill) => (
													<option key={skill.id} value={skill.slug}>
														{skill.name}
													</option>
												))}
											</select>
										)}

										<button className="filter-button">
											{__("Filter", "remote-jobs")}
										</button>
									</div>
								</div>
							)}

							<Notice status="info" isDismissible={false}>
								{__("This is a preview. Click 'Show actual jobs' below to see the real job listings from your site.", "remote-jobs")}
							</Notice>
						</>
					)}

					<div className="editor-preview-toggle">
						<Button
							variant="secondary"
							onClick={() => setIsServerRender(!isServerRender)}
						>
							{__("Show actual jobs", "remote-jobs")}
						</Button>
					</div>
				</div>
			)}
		</div>
	);
}
