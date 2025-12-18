import { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(props: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <div className="">
            <img src="/Logo Tako.png" alt="App Logo" {...props} className={`${props.className ?? ''}`} />
        </div>
    );
}
