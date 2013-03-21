/*
 *	Copyright 2012, Rohan Shah
 *
 *	All rights reserved.
 *
 *	Redistribution and use in source and binary forms, with or without modification, are 
 *	permitted provided that the following conditions are met:
 *
 *	Redistributions of source code must retain the above copyright notice which includes the
 *	name(s) of the copyright holders. It must also retain this list of conditions and the 
 *	following disclaimer. 
 *
 *	Redistributions in binary form must reproduce the above copyright notice, this list 
 *	of conditions and the following disclaimer in the documentation and/or other materials 
 *	provided with the distribution. 
 *
 *	Neither the name of David Book, or buzztouch.com nor the names of its contributors 
 *	may be used to endorse or promote products derived from this software without specific 
 *	prior written permission.
 *
 *	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND 
 *	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED 
 *	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. 
 *	IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, 
 *	INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT 
 *	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR 
 *	PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, 
 *	WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
 *	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY 
 *	OF SUCH DAMAGE. 
 */

#import <UIKit/UIKit.h>
#import <Foundation/Foundation.h>
#import <AVFoundation/AVFoundation.h>
#import "JSON.h"
#import "BT_application.h"
#import "BT_strings.h"
#import "BT_viewUtilities.h"
#import "BT_appDelegate.h"
#import "BT_item.h"
#import "BT_debugger.h"
#import "BT_viewControllerManager.h"
#import "DV_Flashlight_feature.h"

@implementation Dv_flashlight_feature

@synthesize AVSession;

//viewDidLoad
-(void)viewDidLoad{
	[BT_debugger showIt:self theMessage:@"viewDidLoad"];
	[super viewDidLoad];
    
    // SIMPLE BUTTON CODE (CAN BE USED INSTEAD OF IMAGE)
    
    /*
     
     //allocate the view
     self.view = [[UIView alloc] initWithFrame:[[UIScreen mainScreen] applicationFrame]];
     
     //set the view's background color
     self.view.backgroundColor = [UIColor whiteColor];
     
     //create the button
     UIButton *button = [UIButton buttonWithType:UIButtonTypeRoundedRect];
     
     //set the position of the button
     button.frame = CGRectMake(100, 170, 100, 30);
     
     //set the button's title
     [button setTitle:@"Click Me!" forState:UIControlStateNormal];
     
     //listen for clicks
     [button addTarget:self action:@selector(buttonPressed) 
     forControlEvents:UIControlEventTouchUpInside];
     
     //add the button to the view
     [self.view addSubview:button];
     }
     
     -(void)buttonPressed {
     NSLog(@"Button Pressed!");
     
     */
    
    
    [self setView:[[[UIView alloc] initWithFrame:[[UIScreen mainScreen] applicationFrame]] autorelease]];
    
    AVCaptureDevice *device = [AVCaptureDevice defaultDeviceWithMediaType:AVMediaTypeVideo];
    
    // If torch supported, add button to toggle flashlight on/off
    if ([device hasTorch] == YES)
    {
        flashlightButton = [[UIButton alloc] initWithFrame:CGRectMake(10, 60, 300, 300)];
        [flashlightButton setBackgroundImage:[UIImage imageNamed:@"dv_on.png"] forState:UIControlStateNormal];
        [flashlightButton addTarget:self action:@selector(buttonPressed:) forControlEvents: UIControlEventTouchUpInside];      
        
        [[self view] addSubview:flashlightButton];
    }
    
}



//view will appear
-(void)viewWillAppear:(BOOL)animated{
	[super viewWillAppear:animated];
	[BT_debugger showIt:self theMessage:@"viewWillAppear"];
	
	//flag this as the current screen
	BT_appDelegate *appDelegate = (BT_appDelegate *)[[UIApplication sharedApplication] delegate];
	appDelegate.rootApp.currentScreenData = self.screenData;
	
	//setup navigation bar and background
	[BT_viewUtilities configureBackgroundAndNavBar:self theScreenData:[self screenData]];
	
}

// TOGGLE FLASHLIGHT
- (void)toggleFlashlight
{
	AVCaptureDevice *device = [AVCaptureDevice defaultDeviceWithMediaType:AVMediaTypeVideo];
    
    if (device.torchMode == AVCaptureTorchModeOff) 
    {
        
        AVCaptureSession *session = [[AVCaptureSession alloc] init];
        
        AVCaptureDeviceInput *input = [AVCaptureDeviceInput deviceInputWithDevice:device error: nil];
        [session addInput:input];
        
        AVCaptureVideoDataOutput *output = [[AVCaptureVideoDataOutput alloc] init];
        [session addOutput:output];
        
        [session beginConfiguration];
        [device lockForConfiguration:nil];
        
        [device setTorchMode:AVCaptureTorchModeOn];
        
        [device unlockForConfiguration];
        [session commitConfiguration];
        
        [session startRunning];
        
        [self setAVSession:session];
        
        [output release];
    }
    else 
    {
        [AVSession stopRunning];
        [AVSession release], AVSession = nil;
    }
}    

// BUTTON IMAGES
- (void)buttonPressed:(UIButton *)button
{
    if (button == flashlightButton)
    {
        if (flashlightOn == NO)
        {
            flashlightOn = YES;
            [flashlightButton setBackgroundImage:[UIImage imageNamed:@"dv_off.png"] forState:UIControlStateNormal];
            
        }
        else 
        {    	
            flashlightOn = NO;
            [flashlightButton setBackgroundImage:[UIImage imageNamed:@"dv_on.png"] forState:UIControlStateNormal];    
        }
        
		[self toggleFlashlight];
        
    }
}

// DEALLOC
- (void)dealloc 
{
	[flashlightButton release];
	if (AVSession != nil)
        [AVSession release];
    
	[super dealloc];
}

@end

