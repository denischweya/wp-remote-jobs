import { useBlockProps } from '@wordpress/block-editor';
import { TextControl, TextareaControl, SelectControl, RadioControl, Button } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { RichText } from '@wordpress/block-editor';
import Select from 'react-select';
import './editor.scss';

export default function Edit() {
    const [jobTitle, setJobTitle] = useState('');
    const [jobCategory, setJobCategory] = useState('');
    const [jobSkills, setJobSkills] = useState([]);
    const [isWorldwide, setIsWorldwide] = useState('');
    const [jobLocation, setJobLocation] = useState('');
    const [employmentType, setEmploymentType] = useState('');
    const [salaryRange, setSalaryRange] = useState('');
    const [jobDescription, setJobDescription] = useState('');
    const [benefits, setBenefits] = useState([]);

    const [categories, setCategories] = useState([]);
    const [skills, setSkills] = useState([]);
    const [locations, setLocations] = useState([]);
    const [employmentTypes, setEmploymentTypes] = useState([]);
    const [benefitOptions, setBenefitOptions] = useState([]);
    const [salaryRanges, setSalaryRanges] = useState([]);
    const [countries, setCountries] = useState([]);

    const [submitStatus, setSubmitStatus] = useState('');

    const blockProps = useBlockProps();

    useEffect(() => {
        // Fetch taxonomy terms
        apiFetch({ path: '/wp/v2/job_category' }).then(terms => setCategories(terms.map(term => ({ label: term.name, value: term.id }))));
        apiFetch({ path: '/wp/v2/job_skills' }).then(terms => setSkills(terms.map(term => ({ label: term.name, value: term.id }))));
        apiFetch({ path: '/wp/v2/job_location' }).then(terms => setLocations(terms.map(term => ({ label: term.name, value: term.id }))));
        apiFetch({ path: '/wp/v2/employment_type' }).then(terms => setEmploymentTypes(terms.map(term => ({ label: term.name, value: term.id }))));
        apiFetch({ path: '/wp/v2/benefits' }).then(terms => setBenefitOptions(terms.map(term => ({ label: term.name, value: term.id }))));
        apiFetch({ path: '/wp/v2/salary_range' }).then(terms => setSalaryRanges(terms.map(term => ({ label: term.name, value: term.id }))));
        apiFetch({ path: '/wp-remote-jobs/v1/countries' }).then(data => {
            const countryOptions = Object.entries(data).map(([code, name]) => ({ value: code, label: name }));
            setCountries(countryOptions);
        });
    }, []);

    const handleSubmit = async (event) => {
        event.preventDefault();
        setSubmitStatus('Submitting...');

        try {
            const response = await apiFetch({
                path: '/wp/v2/jobs',
                method: 'POST',
                data: {
                    title: jobTitle,
                    content: jobDescription,
                    status: 'pending',
                    job_category: [jobCategory],
                    job_skills: jobSkills,
                    employment_type: [employmentType],
                    benefits: benefits,
                    salary_range: [salaryRange],
                    meta: {
                        _worldwide: isWorldwide,
                        _job_location: jobLocation,
                    },
                    job_location: isWorldwide === 'no' ? [jobLocation] : [],
                },
            });

            if (response) {
                setSubmitStatus('Job submitted successfully!');
                // Reset form fields
                setJobTitle('');
                setJobCategory('');
                setJobSkills([]);
                setIsWorldwide('');
                setJobLocation('');
                setEmploymentType('');
                setSalaryRange('');
                setJobDescription('');
                setBenefits([]);
            }
        } catch (error) {
            setSubmitStatus('Error submitting job. Please try again.');
            console.error('Error:', error);
        }
    };

    return (
        <div { ...blockProps }>
            <form onSubmit={handleSubmit}>
                <TextControl
                    label={__('Job Title', 'submit-job')}
                    value={jobTitle}
                    onChange={setJobTitle}
                />
                <SelectControl
                    label={__('Job Category', 'submit-job')}
                    value={jobCategory}
                    options={[{ label: 'Select a category', value: '' }, ...categories]}
                    onChange={setJobCategory}
                />
                <SelectControl
                    label={__('Skills', 'submit-job')}
                    multiple
                    value={jobSkills}
                    options={[{ label: 'Select skills', value: '' }, ...skills]}
                    onChange={(selectedSkills) => setJobSkills(selectedSkills.filter(skill => skill !== ''))}
                />
                <RadioControl
                    label={__('Is position open worldwide?', 'submit-job')}
                    selected={isWorldwide}
                    options={[
                        { label: 'Yes', value: 'yes' },
                        { label: 'No', value: 'no' },
                    ]}
                    onChange={setIsWorldwide}
                />
                {isWorldwide === 'no' && (
                    <Select
                        className="job-location-select"
                        classNamePrefix="select"
                        options={countries}
                        value={countries.find(country => country.value === jobLocation)}
                        onChange={(selectedOption) => setJobLocation(selectedOption.value)}
                        placeholder={__('Select a country', 'submit-job')}
                    />
                )}
                <SelectControl
                    label={__('Employment Type', 'submit-job')}
                    value={employmentType}
                    options={[{ label: 'Select employment type', value: '' }, ...employmentTypes]}
                    onChange={setEmploymentType}
                />
                <SelectControl
                    label={__('Salary Range', 'submit-job')}
                    value={salaryRange}
                    options={[{ label: 'Select Salary Range', value: '' }, ...salaryRanges]}
                    onChange={setSalaryRange}
                />
                <RichText
                    tagName="div"
                    multiline="p"
                    label={__('Job Description', 'submit-job')}
                    value={jobDescription}
                    onChange={setJobDescription} 
                    placeholder={__('Enter job description here...', 'submit-job')}
                />
                <SelectControl
                    label={__('Benefits', 'submit-job')}
                    multiple
                    value={benefits}
                    options={benefitOptions}
                    onChange={setBenefits}
                />
                <Button isPrimary type="submit">
                    {__('Submit Job', 'submit-job')}
                </Button>
            </form>
            {submitStatus && <p>{submitStatus}</p>}
        </div>
    );
}