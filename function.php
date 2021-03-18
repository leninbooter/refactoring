<?php

class TravelPaymentCalculations {

    private function getJourneyPassengers(
        $getSummaryRequest, Journey $journey, TripFare $tripFare, string $selectedFare, $travelType, $switchedDiscounts = null
    )
    {
        $direction = $journey->getOutgoingType() ? 'I' : 'V';
        $passengerTypes = $this->passengerTypeSessionRepository->get();
        $passengers = new Collection();
        $travellersDiscounts = [];
        $tripFees = $tripFare->getFeeTypes();
        $tripFees = is_array($tripFees) ? $tripFees:array($tripFees);
        foreach ($tripFees as $tripFee) {
            foreach ($tripFee->getDepartureFare() as $fare) {
                if ($direction === $fare->getDirection() || AvailabilityInterface::OUTGOING_TRAVEL_TYPE === $travelType) {
                    foreach ($fare->getTravellers() as $key => $traveller) {
                        if (null === ($passenger = $passengers->get($key))) {
                            $passengerTypeType = $summaryRequestPsxName = $summaryRequestPsxLastName = '';
                            $summaryRequestPassenger = $getSummaryRequest->getPassengers()->get($key);
                            if(!is_null($summaryRequestPassenger)) {
                                $passengerType = $summaryRequestPassenger->getType();
                                $passengerTypeType = is_null($passengerType) ? '':$passengerType->getType();
                                $summaryRequestPsxName = $summaryRequestPassenger->getName();
                                $summaryRequestPsxLastName = $summaryRequestPassenger->getLastName();
                            }

                            $passenger = (new PassengerBreakdown())
                            ->setFare(
                                (new BreakdownLine('fare'))
                                    ->setName($this->getFareName($journey->getFares(), $selectedFare)))
                                ->setType((string)$passengerTypeType)
                            ->setName(sprintf('%s %s', $summaryRequestPsxName, $summaryRequestPsxLastName))
                            ->setId((string)($key+1))
                            ->setSeatData(
                                (new BreakdownLine('seatData'))->setName('')
                                    ->setPrice($traveller->getSeatData()->getNetPriceSeat())
                                    ->setSeatPoints($traveller->getSeatData()->getSeatPoints())
                            );
                        }

                        if ($getSummaryRequest->getInsurance()) {
                            $insurancePrice = $this->getInsurancePrice(
                                $tripFare->getInsuranceDataList(),
                                $journey->getLegs()
                            );
                            if ((float)0 !== $insurancePrice) {
                                $passenger->setInsurance((new BreakdownLine('insurance'))->addPrice($insurancePrice));
                            }
                        }

                        $discountBreakdown = $this->getDiscountBreakdown($traveller, $passengerTypes, $passenger->getDiscounts(), $switchedDiscounts);
                        $extraBreakdown = $this->getExtraBreakdown($traveller, $passengerTypes, $passenger->getExtras());

                        $passenger->setFare(
                            $this->getFareBreakdown(
                                $traveller,
                                $discountBreakdown->getPrice(),
                                $extraBreakdown->getPrice(),
                                $passenger->getFare()
                            )
                        );

                    $passenger
                        ->setExtras($extraBreakdown->getLines())
                        ->setDiscounts($discountBreakdown->getLines());

                        $passengers->set($key, $passenger);
                    }
                }
            }
        }


        return $passengers;
    }
}

?>
