(function() {
  // ველოდებით DOM-ის ჩატვირთვას
  document.addEventListener('DOMContentLoaded', function() {
    // ვიპოვოთ პროდუქტის ნახვის ლინკები
    const updateProductLinks = () => {
      const productLinks = document.querySelectorAll('a.row-title, a.view');
      
      productLinks.forEach(link => {
        const originalUrl = link.getAttribute('href');
        if (originalUrl && originalUrl.includes('post.php?post=')) {
          // ვიღებთ პროდუქტის ID-ს
          const productId = originalUrl.match(/post=(\d+)/)[1];
          
          // ვიღებთ პროდუქტის slug-ს
          fetch(`/wp-json/wp/v2/product/${productId}`)
            .then(response => response.json())
            .then(product => {
              // ვცვლით ლინკს React აპლიკაციის URL-ით
              const newUrl = `/product/${product.slug}`;
              link.setAttribute('href', newUrl);
              
              // ვხსნით ახალ ტაბში
              link.setAttribute('target', '_blank');
            })
            .catch(error => console.error('Error fetching product:', error));
        }
      });
    };

    // თავდაპირველი გაშვება
    updateProductLinks();

    // ვამატებთ MutationObserver-ს დინამიური ცვლილებებისთვის
    const observer = new MutationObserver(updateProductLinks);
    
    const targetNode = document.querySelector('#wpbody-content');
    if (targetNode) {
      observer.observe(targetNode, {
        childList: true,
        subtree: true
      });
    }
  });
})();