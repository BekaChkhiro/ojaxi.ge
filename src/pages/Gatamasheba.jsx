import React, { useState } from 'react';

const Gatamasheba = () => {
    const [formData, setFormData] = useState({
        phone: '',
        first_name: '',
        last_name: '',
        acceptTerms: false
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [message, setMessage] = useState('');
    const [isSuccess, setIsSuccess] = useState(false);
    const [postTitle, setPostTitle] = useState('');
    const [copySuccess, setCopySuccess] = useState(false);

    const handleChange = (e) => {
        const value = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
        
        if (e.target.name === 'phone') {
            const numbersOnly = value.replace(/[^\d]/g, '');
            const truncated = numbersOnly.slice(0, 9);
            setFormData({
                ...formData,
                [e.target.name]: truncated
            });
            return;
        }

        setFormData({
            ...formData,
            [e.target.name]: value
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        setMessage('');
        setIsSuccess(false);

        if (formData.phone.length !== 9 || !formData.phone.startsWith('5')) {
            setMessage('გთხოვთ შეიყვანოთ ვალიდური ტელეფონის ნომერი (9 ციფრი, იწყება 5-ით)');
            setIsSubmitting(false);
            return;
        }

        try {
            const response = await fetch('/wp-json/custom/v1/gatamasheba', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    phone: formData.phone,
                    first_name: formData.first_name,
                    last_name: formData.last_name
                })
            });

            if (response.ok) {
                const data = await response.json();
                console.log('Server response:', data);
                setPostTitle(data.post_title || '');
                setMessage('გილოცავთ! თქვენ წარმატებით დარეგისტრირდით გათამაშებაში');
                setFormData({ phone: '', first_name: '', last_name: '', acceptTerms: false });
                setIsSuccess(true);
            } else {
                const errorData = await response.json();
                if (errorData.code === 'phone_exists') {
                    setMessage('ეს ტელეფონის ნომერი უკვე დარეგისტრირებულია გათამაშებაში');
                } else {
                    setMessage('დაფიქსირდა შეცდომა, გთხოვთ სცადოთ თავიდან');
                }
            }
        } catch (error) {
            setMessage('დაფიქსირდა შეცდომა, გთხოვთ სცადოთ თავიდან');
        }

        setIsSubmitting(false);
    };

    const handleCopy = async () => {
        try {
            if (postTitle) {
                await navigator.clipboard.writeText(postTitle);
                setCopySuccess(true);
                setTimeout(() => setCopySuccess(false), 2000);
            }
        } catch (err) {
            console.error('Failed to copy text: ', err);
            const textArea = document.createElement('textarea');
            textArea.value = postTitle;
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                setCopySuccess(true);
                setTimeout(() => setCopySuccess(false), 2000);
            } catch (e) {
                console.error('Fallback copy failed:', e);
            }
            document.body.removeChild(textArea);
        }
    };

    if (isSuccess) {
        return (
            <div className="min-h-screen bg-gray-100 py-6 sm:py-12 px-3 sm:px-6 lg:px-8">
                <div className="max-w-md mx-auto bg-white rounded-lg shadow-md p-4 sm:p-8 text-center">
                    <div className="text-green-600 mb-4">
                        <svg className="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 className="text-xl sm:text-2xl font-bold text-gray-900 mb-4">
                        გილოცავთ!
                    </h2>
                    <p className="text-[#107440] mb-6">
                        თქვენ წარმატებით დარეგისტრირდით გათამაშებაში
                    </p>
                    <div className="flex flex-col sm:flex-row items-center justify-center gap-2 text-gray-600 mb-6">
                        <p className="text-sm sm:text-base">ბილეთის ნომერი: <span className="font-bold text-black text-base sm:text-lg uppercase">{postTitle}</span></p>
                        <button
                            onClick={handleCopy}
                            className="relative p-1.5 hover:bg-gray-100 rounded-full transition-colors group"
                            title="კოპირება"
                        >
                            {copySuccess ? (
                                <span className="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded">
                                    დაკოპირდა!
                                </span>
                            ) : null}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                            </svg>
                        </button>
                    </div>

                    <p className="text-red-600 mb-6 text-base sm:text-lg font-bold">
                        დასადასტურებლად გთხოვთ გადახვიდეთ ფეისბუქ გვერდზე და გაგზავნოთ თქვენი ბილეთის ნომერი
                    </p>  

                    <a
                        href={`https://www.facebook.com/ojaxi.ge/`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors mt-4 inline-block"
                    >
                        ფეისბუქ გვერდი
                    </a>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-100 py-6 sm:py-12 px-3 sm:px-6 lg:px-8">
            <div className="max-w-md mx-auto bg-white rounded-lg shadow-md p-4 sm:p-8">
                <h2 className="text-xl sm:text-2xl font-bold text-center text-gray-900 mb-6 sm:mb-8">
                    გათამაშებაში მონაწილეობა
                </h2>
                
                {message && (
                    <div className={`mb-6 p-4 rounded-md ${
                        message.includes('გილოცავთ') 
                            ? 'bg-green-50 text-green-800' 
                            : 'bg-red-50 text-red-800'
                    }`}>
                        {message}
                    </div>
                )}
                
                <form onSubmit={handleSubmit} className="space-y-4 sm:space-y-6">
                    <div>
                        <label 
                            htmlFor="phone" 
                            className="block text-sm font-medium text-gray-700 mb-1"
                        >
                            ტელეფონი
                        </label>
                        <div className="relative">
                            <span className="absolute left-3 top-2 text-gray-500">+995</span>
                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                value={formData.phone}
                                onChange={handleChange}
                                required
                                className="appearance-none block w-full pl-16 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="5XX XX XX XX"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label 
                                htmlFor="first_name" 
                                className="block text-sm font-medium text-gray-700 mb-1"
                            >
                                სახელი
                            </label>
                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                value={formData.first_name}
                                onChange={handleChange}
                                required
                                className="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="სახელი"
                            />
                        </div>

                        <div>
                            <label 
                                htmlFor="last_name" 
                                className="block text-sm font-medium text-gray-700 mb-1"
                            >
                                გვარი
                            </label>
                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                value={formData.last_name}
                                onChange={handleChange}
                                required
                                className="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="გვარი"
                            />  
                        </div>
                    </div>

                    <div className="flex items-start sm:items-center">
                        <input
                            type="checkbox"
                            id="acceptTerms"
                            name="acceptTerms"
                            checked={formData.acceptTerms}
                            onChange={handleChange}
                            required
                            className="h-4 w-4 mt-1 sm:mt-0 text-[#167336] focus:ring-[#167336] border-gray-300 rounded"
                        />
                        <label 
                            htmlFor="acceptTerms" 
                            className="ml-2 block text-xs sm:text-sm text-gray-700"
                        >
                            ვეთანხმები <a href="/terms-and-condition" className="text-[#167336]">წესებს და პირობებს</a>
                        </label>
                    </div>

                    <button
                        type="submit"
                        disabled={isSubmitting || !formData.acceptTerms}
                        className={`w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white 
                            ${(isSubmitting || !formData.acceptTerms)
                                ? 'bg-gray-400 cursor-not-allowed' 
                                : 'bg-[#167336] hover:bg-[#167336] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500'
                            }`}
                    >
                        {isSubmitting ? 'იგზავნება...' : 'გაგზავნა'}
                    </button>
                </form>
            </div>
        </div>
    );
};

export default Gatamasheba; 