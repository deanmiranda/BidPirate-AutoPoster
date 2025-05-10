<?php get_header(); ?>

<div id="main-content" style="background-color: #f5f5f5;">
    <div class="container auction-post-container">
        <div id="content-area" class="clearfix">

            <div class="auction-header">
                <a href="/search" class="back-to-search">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="#FF6B35" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="12,2 22,22 2,22" transform="rotate(-90,12,12)" />
                    </svg>
                    Back to Search</a>
                <div class="auction-title">Foreclosure Auction </div>
                <a href="#" class="watchlist-btn"><span>+</span>WATCHLIST</a>
            </div>

            <div class="auction-meta">
                <div class="meta-left">
                    <div class="meta-title"><?php the_title(); ?></div>
                    <p><?php echo get_post_meta(get_the_ID(), 'address', true); ?>,
                        <?php echo get_post_meta(get_the_ID(), 'city', true); ?>,
                        <?php echo get_post_meta(get_the_ID(), 'state', true); ?>
                        <?php echo get_post_meta(get_the_ID(), 'zip', true); ?>
                    </p>
                    <div class="meta-split">
                        <p><strong>County:</strong> <?php echo get_post_meta(get_the_ID(), 'county', true); ?> </p>

                        <p><strong>Book / Page:</strong> <?php echo get_post_meta(get_the_ID(), 'bookpage', true); ?>
                        </p>
                    </div>
                    
                </div>
                <div class="meta-right">
                    <div class="mini-group">
                        <div class="mini-left">
                            <p><strong>Date:</strong> <?php echo get_post_meta(get_the_ID(), 'date', true); ?></p>
                            <p><strong>Time:</strong> <?php echo get_post_meta(get_the_ID(), 'time', true); ?></p>

                        </div>
                        <div class="mini-right">
                            <span class="status-active">
                                <?php echo get_post_meta(get_the_ID(), 'status', true); ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <p><strong>Previous Auction Dates:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'previousdate', true); ?></p>

                    </div>
                </div>

            </div>

            <div class="accordion-section">
                <div class="accordion-toggle">Property Summary 
                <div class='accordion-icon-wrapper'>    
                    <span class="accordion-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <polygon points="50,15 90,85 10,85" fill="#FF6B35" />
                        </svg>
                    </span>
                </div>
                </div>
                <div class="section-content grid-two">
                    <div class="section-content-left">
                        <p><strong>Property Type:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'propertytypedetail', true); ?></p>
                        <p><strong>Year Built:</strong> <?php echo get_post_meta(get_the_ID(), 'yearbuilt', true); ?>
                        </p>
                        <p><strong>Condition:</strong> <?php echo get_post_meta(get_the_ID(), 'condition', true); ?></p>
                        <p><strong>Bedrooms:</strong> <?php echo get_post_meta(get_the_ID(), 'bedrooms', true); ?></p>
                        <p><strong>Bathrooms:</strong> <?php echo get_post_meta(get_the_ID(), 'bathrooms', true); ?></p>
                        <p><strong>Square Footage:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'livingareasqft', true); ?></p>
                        <p><strong>Stories:</strong> <?php echo get_post_meta(get_the_ID(), 'stories', true); ?></p>
                        <p><strong>Garage Type:</strong> <?php echo get_post_meta(get_the_ID(), 'garagetype', true); ?>
                        </p>
                        <p><strong>Garage Spaces:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'garagespaces', true); ?></p>
                        <p><strong>Residential Units:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'residentialunits', true); ?></p>
                        <p><strong>Building Style:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'buildingstyle', true); ?></p>
                        <p><strong>Architectural Style:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'architectualstyle', true); ?></p>
                        <p><strong>Features:</strong> <?php echo get_post_meta(get_the_ID(), 'features', true); ?></p>
                    </div>
                    <div class="section-content-right">
                        <p><strong>Last Update:</strong> <?php echo get_post_meta(get_the_ID(), 'LastUpdate', true); ?>
                        </p>

                        <div class="property-map">
                            <iframe
                                width="100%"
                                height="250"
                                frameborder="0"
                                style="border:0"
                                src="https://maps.google.com/maps?q=New+York,+NY&output=embed"
                                allowfullscreen
                                loading="lazy"
                            ></iframe>
                        </div>

                    </div>
                </div>
            </div>

            <div class="accordion-section">
                <div class="accordion-toggle">Location 
                <div class='accordion-icon-wrapper'>    
                    <span class="accordion-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <polygon points="50,15 90,85 10,85" fill="#FF6B35" />
                        </svg>
                    </span>
                </div>
                </div>
                <div class="section-content grid-two">
                    <div class="section-content-left">
                        <p><strong>Lot Size Acres</strong>
                            <?php echo get_post_meta(get_the_ID(), 'Lot Size Acres', true); ?></p>
                        <p><strong>Zoning Code</strong> <?php echo get_post_meta(get_the_ID(), 'zoningcode', true); ?>
                        </p>
                        <p><strong>Property Vacant:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'propertyvacant', true); ?></p>
                        <p><strong>Mailing Vacant:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'mailingvacant', true); ?></p>
                    </div>
                    <div class="section-content-right">
                        <p><strong>Land Use Code:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'landusecode', true); ?>
                        </p>
                        <p><strong>Map / Lot #:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'maplotnumber', true); ?>
                        </p>
                        <p><strong>School District:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'schooldistrict', true); ?></p>
                        <p><strong>Schools Names:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'schoolsnames', true); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-section">
                <div class="accordion-toggle">Building / Utilities 
                <div class='accordion-icon-wrapper'>    
                    <span class="accordion-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <polygon points="50,15 90,85 10,85" fill="#FF6B35" />
                        </svg>
                    </span>
                </div>
                </div>

                <div class="section-content grid-two">
                    <div class="section-content-left">
                        <p><strong>AC Source:</strong> <?php echo get_post_meta(get_the_ID(), 'acsource', true); ?></p>
                        <p><strong>Basement Type:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'basementtype', true); ?>
                        </p>
                        <p><strong>Foundation:</strong> <?php echo get_post_meta(get_the_ID(), 'foundation', true); ?>
                        </p>
                        <p><strong>Exterior Walls:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'exteriorwalls', true); ?></p>
                        <p><strong>Construction Type:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'constructiontype', true); ?></p>
                        <p><strong>Heat Source:</strong> <?php echo get_post_meta(get_the_ID(), 'heatsource', true); ?>
                        </p>
                    </div>
                    <div class="section-content-right">
                        <p><strong>Heating Fuel:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'heatingfuel', true); ?>
                        </p>
                        <p><strong>Roof Cover:</strong> <?php echo get_post_meta(get_the_ID(), 'roofcover', true); ?>
                        </p>
                        <p><strong>Roof Type:</strong> <?php echo get_post_meta(get_the_ID(), 'rooftype', true); ?></p>
                        <p><strong>Sewer:</strong> <?php echo get_post_meta(get_the_ID(), 'sewer', true); ?></p>
                        <p><strong>Water Service:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'waterservice', true); ?>
                        </p>
                        <p><strong>Fireplace Count:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'fireplacecount', true); ?></p>
                    </div>
                </div>
            </div>

            <div class="accordion-section">
                <div class="accordion-toggle">Financials
                    <div class='accordion-icon-wrapper'>    
                        <span class="accordion-icon" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="50,15 90,85 10,85" fill="#FF6B35" />
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="section-content grid-two">
                    <div class="section-content-left">
                        <p><strong>Current Lender:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'currentlender', true); ?></p>
                        <p><strong>Last Loan
                                Date:</strong><?php echo get_post_meta(get_the_ID(), 'lastloaddate', true); ?>
                        </p>
                        <p><strong>Last Sale Date:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'lastsaledate', true); ?>
                        </p>
                        <p><strong>Last Sold
                                Price:</strong><?php echo get_post_meta(get_the_ID(), 'lastsaleprice', true); ?></p>
                        <p><strong>Mortgage
                                Date:</strong><?php echo get_post_meta(get_the_ID(), 'mortgagedate', true); ?>
                        </p>
                        <p><strong>Mortgage
                                Price:</strong><?php echo get_post_meta(get_the_ID(), 'mortgageloanamount', true); ?>
                        </p>
                    </div>
                    <div class="section-content-right">
                        <p><strong>Last Sale Doc Type:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'lastsaledoctype', true); ?></p>
                        <p><strong>Foreclosure Law Office:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'foreclosurelawoffice', true); ?></p>
                        <p><strong>Estimated
                                Value:</strong><?php echo get_post_meta(get_the_ID(), 'estimatedvalue', true); ?></p>
                        <p><strong>Taxes:</strong><?php echo get_post_meta(get_the_ID(), 'taxamount', true); ?>
                            (<?php echo get_post_meta(get_the_ID(), 'tax_year', true); ?>)</p>
                        <p><strong>Assessed Value:</strong>
                            <?php echo get_post_meta(get_the_ID(), 'assessedtotalvalue', true); ?></p>
                    </div>
                    <div class="section-content-tri-column">
                        <div class="column-one">Zillow Estimate</div>
                        <div class="column-two">RedFin Estimate</div>
                        <div class="column-three">BidPirate Estimate</div>
                    </div>
                </div>
            </div>
            <div class="accordion-section">
                <div class="accordion-toggle">
                    Auctioneers

                    <div class="accordion-icon-wrapper">
                        <span class="accordion-icon" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <polygon points="50,15 90,85 10,85" fill="#FF6B35" />
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="section-content">
                    <div class="auctioneer-block">
                        <div class="section-content grid-two grid-two-thirds">
                            <div class="section-content-left">
                                <p><strong>Name:</strong> <?php echo get_post_meta(get_the_ID(), 'auctioneer', true); ?></p>
                                <p><strong>Deposit:</strong> <?php echo get_post_meta(get_the_ID(), 'deposit', true); ?></p>
                                <p><strong>Auctioneer Notes:</strong> <?php echo get_post_meta(get_the_ID(), 'auctioneernotes', true); ?></p>
                            </div>
                            <div class="section-content-right">
                                <div class="auctioneer-links">
                                    <a class="btn-link" href="<?php echo esc_url(get_post_meta(get_the_ID(), 'listinglink', true)); ?>" target="_blank">Auction Listing Link</a>
                                    <a class="btn-link" href="<?php echo esc_url(get_post_meta(get_the_ID(), 'auctiontermslink', true)); ?>" target="_blank">Auctioneer Website</a>
                                </div>
                            </div>
                        </div>
                        <div class="bottom-shelf">
                            <p><strong>Terms:</strong> 
                            Cashier's or certified check in the sum of $5,000.00 as a deposit must be shown @ the time and place of the sale in order to qualify as a bidder. No CASH. No personal checks will be accepted. Cashier-certified checks should be made out to whomever is going to bid @ the auction. The balance to be paid within thirty (30) days @ the law offices of Korde & Associates, P.C., 900 Chelmsford Street, Lowell, MA 01851, Attorney for the Mortgagee.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

        </div>
    </div>
</div>

<?php get_footer(); ?>