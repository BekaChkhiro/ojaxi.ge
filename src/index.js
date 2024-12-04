import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';

// დავამატოთ console.log დებაგინგისთვის
console.log("React script loading...");

window.addEventListener('load', () => {
    const rootElement = document.getElementById("root");
    console.log("Root element:", rootElement); // შევამოწმოთ root ელემენტი

    if (rootElement) {
        try {
            const root = ReactDOM.createRoot(rootElement);
            root.render(
                <App />
            );
            console.log("React app rendered successfully");
        } catch (error) {
            console.error("Error rendering React app:", error);
        }
    } else {
        console.error("Root element not found");
    }
});
