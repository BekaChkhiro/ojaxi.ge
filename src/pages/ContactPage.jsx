import React from 'react';

const ContactPage = () => {
  return (
    <div className='flex justify-between items-center'>
      <div className='w-1/3 flex flex-col justify-center gap-2 items-center'>
        <span>ტელეფონი</span>
        <span>+995 598 14 95 96</span>
      </div>

      <div className='w-1/3 flex flex-col justify-center gap-2 items-center'>
        <span>ელ-ფოსტა</span>
        <span>info@ojaxi.ge</span>
      </div>

      <div className='w-1/3 flex flex-col justify-center gap-2 items-center'>
        <span>მისამართი</span>
        <span>თბილისი, პეკინის #44</span>
      </div>
      
    </div>
  )
}

export default ContactPage;